/* XXDQ Selector Tool — terminal recommendation calculator
 * Pure vanilla JS, no dependencies.
 *
 * Data model (spec §一–§二):
 *   TERMINAL_DB   — 4 series (DT / SC / RV / DTL), each row = { area, model, hole, mold?, baseAmp }
 *   TEMP_K       — 环境温度修正系数 (25°C=1.00 → 60°C=0.65, step 5°C)
 *   INSTALL_K    — 安装方式修正系数 (air=1.00 / pipe=0.85 / buried=0.80)
 *   MATERIAL_K   — 导线材质修正系数 (copper=1.00 / aluminum=0.78)
 *                  DTL type carries its own transition penalty 0.85 regardless of material.
 *
 * Calculation paths (spec §四):
 *   Path A — user supplied power kW → compute I_target → pick smallest spec baseAmp ≥ I_target
 *   Path B — area lookup → back-calc P_max from corrected ampacity
 *
 * Output shape: spec §五.
 */
(function () {
  'use strict';

  // ── Language strings ───────────────────────────────────────────────────────
  // The block renders its labels server-side; only the strings the JS
  // produces itself need a dictionary here. The active language is read
  // from the root element's `data-lang` attribute so the same JS file
  // serves both the Chinese and the English site.
  const STRINGS = {
    zh: {
      phaseSingle: '单相',
      phaseThree: '三相',
      errVoltage: '请填写有效的系统电压。',
      errPowerFactor: '功率因数需在 0–1 之间。',
      errOverload: (tc, type, max) => `目标电流 ${tc} A 超出 ${type} 系列最大基准载流量 ${max} A，请联系客服定制或降低负载。`,
      errType: (type) => `端子类型 ${type} 不存在，请检查选择。`,
      errAreaLow: (area, min) => `截面 ${area} mm² 低于最小标准规格 ${min} mm²，请联系客服。`,
      errAreaHigh: (area, max) => `截面 ${area} mm² 超出标准范围（最大 ${max} mm²），请联系我们定制。`,
      warnAmpacity: '当前选型载流量不足，建议升级规格或降低负载。',
      recModel: '推荐型号：',
      recModelRounded: '（向上取整）',
      notApplicable: '不适用',
      conclusionFail: ' 载流量不足',
      conclusionPass: ' 满足',
      backCalc: '—（按截面反算）',
      noteRounded: (area, model, max) => `提示：您输入的 ${area} mm² 介于标准规格之间，已为您匹配更大的 ${model}（${max} mm²）以保证安全裕度。`,
      noteDtl: '提示：DTL 为铜铝过渡端子，专用于铜线与铝线的连接，禁止与纯铜或纯铝场合混用。',
      notePower: (kw, v, phase, i, model) => `提示：按 ${kw} kW / ${v} V ${phase} 计算目标电流 ${i} A，${model} 满足要求。`,
    },
    en: {
      phaseSingle: 'Single phase',
      phaseThree: 'Three phase',
      errVoltage: 'Please enter a valid system voltage.',
      errPowerFactor: 'The power factor must be between 0 and 1.',
      errOverload: (tc, type, max) => `The target current of ${tc} A exceeds the maximum base ampacity of ${max} A in the ${type} series. Contact us for a custom solution or reduce the load.`,
      errType: (type) => `The terminal type ${type} does not exist — please check your selection.`,
      errAreaLow: (area, min) => `A ${area} mm² cross-section is below the smallest standard size of ${min} mm². Please contact us.`,
      errAreaHigh: (area, max) => `A ${area} mm² cross-section is above the standard range (max. ${max} mm²). Please contact us for a custom solution.`,
      warnAmpacity: 'The selected spec does not carry enough current. Consider a larger size or a lower load.',
      recModel: 'Recommended model: ',
      recModelRounded: ' (rounded up)',
      notApplicable: 'N/A',
      conclusionFail: ' Insufficient ampacity',
      conclusionPass: ' Meets requirement',
      backCalc: '— (back-calculated from the cross-section)',
      noteRounded: (area, model, max) => `Note: the ${area} mm² you entered falls between standard sizes, so we matched the next larger ${model} (${max} mm²) to keep a safe margin.`,
      noteDtl: 'Note: DTL is a copper-to-aluminium transition terminal, used only to join copper and aluminium conductors. Do not use it for pure copper or pure aluminium runs.',
      notePower: (kw, v, phase, i, model) => `Note: based on ${kw} kW / ${v} V ${phase}, the target current is ${i} A and ${model} meets the requirement.`,
    },
  };

  let LANG = STRINGS.zh;

  // ── Data tables (spec §一) ────────────────────────────────────────────────
  const TERMINAL_DB = {
    DT: [ // 铜鼻子 (copper lugs)
      { area: 10,  model: 'DT-10',  hole: 8.5,  mold: 10, baseAmp: 60 },
      { area: 16,  model: 'DT-16',  hole: 8.5,  mold: 12, baseAmp: 80 },
      { area: 25,  model: 'DT-25',  hole: 8.5,  mold: 14, baseAmp: 105 },
      { area: 35,  model: 'DT-35',  hole: 10.5, mold: 16, baseAmp: 130 },
      { area: 50,  model: 'DT-50',  hole: 10.5, mold: 18, baseAmp: 165 },
      { area: 70,  model: 'DT-70',  hole: 12.5, mold: 20, baseAmp: 210 },
      { area: 95,  model: 'DT-95',  hole: 12.5, mold: 22, baseAmp: 255 },
      { area: 120, model: 'DT-120', hole: 14.5, mold: 25, baseAmp: 300 },
      { area: 150, model: 'DT-150', hole: 14.5, mold: 27, baseAmp: 345 },
      { area: 185, model: 'DT-185', hole: 16.5, mold: 30, baseAmp: 395 },
      { area: 240, model: 'DT-240', hole: 16.5, mold: 34, baseAmp: 470 }
    ],
    SC: [ // 冷压端子 (cold-pressed terminals)
      { area: 0.5,  model: 'SC0.5-4',  hole: 4.3,  mold: 1.5, baseAmp: 8 },
      { area: 0.75, model: 'SC0.75-4', hole: 4.3,  mold: 2,   baseAmp: 10 },
      { area: 1.0,  model: 'SC1.0-4',  hole: 4.3,  mold: 2.5, baseAmp: 13 },
      { area: 1.5,  model: 'SC1.5-4',  hole: 4.3,  mold: 3,   baseAmp: 18 },
      { area: 2.5,  model: 'SC2.5-5',  hole: 5.3,  mold: 4,   baseAmp: 25 },
      { area: 4,    model: 'SC4-6',    hole: 6.4,  mold: 5,   baseAmp: 35 },
      { area: 6,    model: 'SC6-6',    hole: 6.4,  mold: 6,   baseAmp: 45 },
      { area: 10,   model: 'SC10-8',   hole: 8.4,  mold: 8,   baseAmp: 60 },
      { area: 16,   model: 'SC16-8',   hole: 8.4,  mold: 10,  baseAmp: 80 },
      { area: 25,   model: 'SC25-8',   hole: 8.4,  mold: 12,  baseAmp: 105 },
      { area: 35,   model: 'SC35-10',  hole: 10.5, mold: 14,  baseAmp: 130 },
      { area: 50,   model: 'SC50-10',  hole: 10.5, mold: 16,  baseAmp: 165 }
    ],
    RV: [ // 预绝缘端子 (pre-insulated)
      { area: 0.5,  model: 'RV1.25-4', hole: 4.3, baseAmp: 8 },
      { area: 0.75, model: 'RV1.25-4', hole: 4.3, baseAmp: 10 },
      { area: 1.0,  model: 'RV1.25-4', hole: 4.3, baseAmp: 13 },
      { area: 1.5,  model: 'RV1.5-4',  hole: 4.3, baseAmp: 18 },
      { area: 2.5,  model: 'RV2.5-5',  hole: 5.3, baseAmp: 25 },
      { area: 4,    model: 'RV4-6',    hole: 6.4, baseAmp: 35 },
      { area: 6,    model: 'RV6-6',    hole: 6.4, baseAmp: 45 }
    ],
    DTL: [ // 铜铝过渡端子 (copper-aluminum transition)
      { area: 16,  model: 'DTL-16',  hole: 8.5,  baseAmp: 80 },
      { area: 25,  model: 'DTL-25',  hole: 8.5,  baseAmp: 105 },
      { area: 35,  model: 'DTL-35',  hole: 10.5, baseAmp: 130 },
      { area: 50,  model: 'DTL-50',  hole: 10.5, baseAmp: 165 },
      { area: 70,  model: 'DTL-70',  hole: 12.5, baseAmp: 210 },
      { area: 95,  model: 'DTL-95',  hole: 12.5, baseAmp: 255 },
      { area: 120, model: 'DTL-120', hole: 14.5, baseAmp: 300 },
      { area: 150, model: 'DTL-150', hole: 14.5, baseAmp: 345 },
      { area: 185, model: 'DTL-185', hole: 16.5, baseAmp: 395 },
      { area: 240, model: 'DTL-240', hole: 16.5, baseAmp: 470 }
    ]
  };

  // 环境温度修正系数 (spec §二)
  const TEMP_K = [
    { maxTemp: 25, k: 1.00 },
    { maxTemp: 30, k: 0.95 },
    { maxTemp: 35, k: 0.90 },
    { maxTemp: 40, k: 0.85 },
    { maxTemp: 45, k: 0.80 },
    { maxTemp: 50, k: 0.75 },
    { maxTemp: 55, k: 0.70 },
    { maxTemp: 60, k: 0.65 }
  ];

  // 安装方式修正系数 (spec §二)
  const INSTALL_K = {
    air:    1.00,  // 空气中
    pipe:   0.85,  // 穿管
    buried: 0.80   // 埋地
  };

  // 导线材质修正系数 (spec §二)
  // DTL type carries a separate transition penalty (0.85) applied on top of
  // the material coefficient; for non-DTL types we use material-based K.
  const MATERIAL_K = {
    copper:   1.00,  // 铜芯
    aluminum: 0.78   // 铝芯
  };
  const DTL_K = 0.85; // 铜铝过渡端子的过渡损耗系数

  // ── Form constants (spec §二) ──────────────────────────────────────────────
  const DEFAULT_POWER_FACTOR = 0.85;
  const VOLTAGE_OPTIONS     = [220, 380, 660];
  const PHASE_OPTIONS = {
    single: { factor: 1 },
    three:  { factor: Math.sqrt(3) },
  };

  // ── Helpers ────────────────────────────────────────────────────────────────
  function getTempK(temp) {
    if (typeof temp !== 'number' || isNaN(temp) || temp < 25) return 1.00;
    for (let i = 0; i < TEMP_K.length; i++) {
      if (temp <= TEMP_K[i].maxTemp) return TEMP_K[i].k;
    }
    return 0.65; // > 60°C 保守取值
  }

  /**
   * Area-mode lookup: find a terminal row from TERMINAL_DB[type].
   * - Exact area match wins.
   * - Otherwise pick the smallest spec strictly greater than `area`
   *   (engineering safety margin: always round up).
   * - Returns { row, exact, range } or out-of-range indicator.
   */
  function findRow(type, area) {
    const rows = TERMINAL_DB[type];
    if (!rows) return null;
    const min = rows[0].area;
    const max = rows[rows.length - 1].area;
    if (area < min) return { outOfRange: 'low', min: min };
    if (area > max) return { outOfRange: 'high', max: max };
    for (let i = 0; i < rows.length; i++) {
      if (rows[i].area === area) return { row: rows[i], exact: true, range: [area, area] };
    }
    for (let i = 0; i < rows.length; i++) {
      if (rows[i].area > area) {
        return { row: rows[i], exact: false, range: [area, rows[i].area] };
      }
    }
    return { outOfRange: 'high', max: max };
  }

  /**
   * Power-mode lookup: find the SMALLEST spec in TERMINAL_DB[type] whose
   * baseAmp >= targetAmp.  Rows are sorted by area ascending, so the first
   * match is the cheapest spec that still meets the electrical requirement.
   *
   * Returns:
   *   { row, index, total }              — match found
   *   { outOfRange: 'high', maxBaseAmp } — every row's baseAmp < targetAmp
   *   null                               — type not in DB
   */
  function findRowByBaseAmp(type, targetAmp) {
    const rows = TERMINAL_DB[type];
    if (!rows) return null;
    for (let i = 0; i < rows.length; i++) {
      if (rows[i].baseAmp >= targetAmp) {
        return { row: rows[i], index: i, total: rows.length };
      }
    }
    return { outOfRange: 'high', maxBaseAmp: rows[rows.length - 1].baseAmp };
  }

  /**
   * Compute target current (A) from load spec (spec §四).
   *   single:  I = P × 1000 / (U × cosφ)
   *   three:   I = P × 1000 / (√3 × U × cosφ)
   */
  function currentFromPower(powerKw, voltage, phase, powerFactor) {
    return (powerKw * 1000) / (PHASE_OPTIONS[phase].factor * voltage * powerFactor);
  }

  /**
   * Back-calc maximum sustained load (kW) from corrected ampacity (spec §四).
   *   single:  P = U × I × cosφ / 1000
   *   three:   P = √3 × U × I × cosφ / 1000
   */
  function powerFromCurrent(amp, voltage, phase, powerFactor) {
    return (PHASE_OPTIONS[phase].factor * voltage * amp * powerFactor) / 1000;
  }

  /**
   * Core calculation engine (spec §四).
   *
   * Input: { area, material, temp, install, type,
   *          voltage, phase, power (optional), powerFactor }
   *
   * Output: { success: true, data: { ... } } | { success: false, message }
   */
  function calculate(input) {
    const { area, material, temp, install, type, voltage, phase, power, powerFactor } = input;

    // ── Correction factors (shared by Path A and Path B) ──────────────────
    const kTemp    = getTempK(temp);
    const kInstall = INSTALL_K[install] || 1.00;
    // DTL type carries its own transition penalty; non-DTL uses material coefficient.
    const kMat     = type === 'DTL' ? DTL_K : (MATERIAL_K[material] || 1.00);
    const K = kTemp * kInstall * kMat;

    // Fields echoed back to the render layer (spec §五).
    const echo = {
      type, material, area, temp, install,
      voltage, phase, powerFactor,
      factors: { temp: kTemp, install: kInstall, material: kMat },
    };

    // ── Path A: user supplied load power kW (spec §四) ────────────────────
    //   1. Compute I_target from power
    //   2. Pick smallest spec with baseAmp >= I_target
    //   3. Apply correction K → actualAmp
    //   4. If actualAmp < I_target → set warning
    const hasPower = (typeof power === 'number') && !isNaN(power) && power > 0;
    if (hasPower) {
      if (!voltage || voltage <= 0) {
        return { success: false, message: LANG.errVoltage };
      }
      if (!powerFactor || powerFactor <= 0 || powerFactor > 1) {
        return { success: false, message: LANG.errPowerFactor };
      }
      const targetCurrent = currentFromPower(power, voltage, phase, powerFactor);

      const found = findRowByBaseAmp(type, targetCurrent);
      if (found && found.outOfRange) {
        return {
          success: false,
          message: LANG.errOverload(targetCurrent.toFixed(1), type, found.maxBaseAmp),
        };
      }
      if (!found) {
        return { success: false, message: LANG.errType(type) };
      }

      const row     = found.row;
      const actualAmp = Math.round(row.baseAmp * K);
      const warning   = targetCurrent > actualAmp
        ? LANG.warnAmpacity
        : null;

      return {
        success: true,
        data: Object.assign({}, echo, {
          model:           row.model,
          hole:            row.hole,
          mold:            row.mold === undefined ? null : row.mold,
          baseAmp:         row.baseAmp,
          actualAmp:       actualAmp,
          targetCurrent:   Math.round(targetCurrent),
          calculatedPower:  Number(power.toFixed(1)),
          warning:         warning,
          // In power-mode the row was chosen to satisfy baseAmp >= targetCurrent;
          // there is no "exact vs range" distinction (always exact in this sense).
          exact:           true,
          range:           [row.area, row.area],
        }),
      };
    }

    // ── Path B: no power supplied — area lookup + back-calc (spec §四) ───
    //   1. Find row by area (exact match or next size up)
    //   2. Apply correction K → actualAmp
    //   3. Back-calc P_max from actualAmp (informational, no warning)
    const found = findRow(type, area);
    if (found && found.outOfRange) {
      return {
        success: false,
        message: found.outOfRange === 'low'
          ? LANG.errAreaLow(area, found.min)
          : LANG.errAreaHigh(area, found.max),
      };
    }

    const row        = found.row;
    const actualAmp  = Math.round(row.baseAmp * K);
    const calculatedPower = Number(powerFromCurrent(actualAmp, voltage, phase, powerFactor).toFixed(1));

    return {
      success: true,
      data: Object.assign({}, echo, {
        model:           row.model,
        hole:            row.hole,
        mold:            row.mold === undefined ? null : row.mold,
        baseAmp:         row.baseAmp,
        actualAmp:       actualAmp,
        targetCurrent:   null,       // no target to compare against (Path B)
        calculatedPower: calculatedPower,
        warning:         null,       // informational only — no failure condition
        exact:           found.exact,
        range:           found.range,
      }),
    };
  }

  // ── DOM helpers ───────────────────────────────────────────────────────────
  function $(s, root) { return (root || document).querySelector(s); }
  function $all(s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); }
  function hide(el) { if (el) el.classList.remove('is-visible'); }
  function show(el) { if (el) el.classList.add('is-visible'); }

  function areaOptionsHtml() {
    const all = [];
    Object.keys(TERMINAL_DB).forEach(t => TERMINAL_DB[t].forEach(r => all.push(r.area)));
    const uniq = Array.from(new Set(all)).sort((a, b) => a - b);
    return uniq.map(a => `<option value="${a}">${a} mm²</option>`).join('');
  }

  function phaseLabel(phase) {
    if (phase === 'single') return LANG.phaseSingle;
    if (phase === 'three') return LANG.phaseThree;
    return phase;
  }

  // ── Render result (spec §六) ──────────────────────────────────────────────
  function renderResult(root, payload) {
    const result = $('.xxdq-selector__result', root);
    const error  = $('.xxdq-selector__error', root);
    hide(result); hide(error);

    if (!payload.success) {
      $('.xxdq-selector__error-msg', error).textContent = payload.message;
      show(error);
      error.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    const d = payload.data;
    const isPathA = d.targetCurrent !== null;  // user supplied power

    // ── 端子选型结果 table ────────────────────────────────────────────────
    // Model badge + title
    const titleEl = $('.xxdq-selector__result-title', result);
    if (titleEl) {
      titleEl.textContent = d.exact
        ? LANG.recModel + d.model
        : LANG.recModel + d.model + LANG.recModelRounded;
    }
    // The badge appears in two places: the head pill AND the first row
    // of the 端子选型结果 table. Fill both.
    $all('.xxdq-selector__model-badge', result).forEach(b => { b.textContent = d.model; });

    // Stat cells
    const setStat = (sel, val) => {
      const el = $(sel, result);
      if (el) el.textContent = val;
    };
    // New DOM: each value <span> carries BOTH the semantic class
    // (--hole/--mold/--base/--actual) AND the shared --value modifier.
    // Select the element directly (no descendant combinator).
    setStat('.xxdq-selector__stat--hole',  d.hole + ' mm');
    const moldEl = $('.xxdq-selector__stat--mold', result);
    if (moldEl) {
      moldEl.textContent = d.mold === null ? LANG.notApplicable : d.mold + ' mm';
      moldEl.classList.toggle('xxdq-selector__stat-value--muted', d.mold === null);
    }
    setStat('.xxdq-selector__stat--base',   d.baseAmp + ' A');
    setStat('.xxdq-selector__stat--actual', d.actualAmp + ' A');

    // Conclusion cell with pass/fail badge
    const conclusion = $('.xxdq-selector__terminal-conclusion', result);
    if (conclusion) {
      conclusion.classList.remove('is-fail');
      if (d.warning) {
        conclusion.innerHTML = '<span class="xxdq-selector__conclusion-icon xxdq-selector__conclusion-icon--fail">⚠</span>' + LANG.conclusionFail;
        conclusion.classList.add('is-fail');
      } else {
        conclusion.innerHTML = '<span class="xxdq-selector__conclusion-icon xxdq-selector__conclusion-icon--pass">✅</span>' + LANG.conclusionPass;
      }
    }

    // Correction factors strip
    const f = d.factors;
    const setFactor = (sel, v) => { const el = $(sel, result); if (el) el.textContent = v.toFixed(2); };
    setFactor('.xxdq-selector__factor--temp',     f.temp);
    setFactor('.xxdq-selector__factor--install',  f.install);
    setFactor('.xxdq-selector__factor--material', f.material);

    // ── 功率信息 table ───────────────────────────────────────────────────
    const pv = (sel, val) => { const el = $(sel, result); if (el) el.textContent = val; };

    pv('.xxdq-selector__pv--voltage',  d.voltage + ' V');
    pv('.xxdq-selector__pv--phase',    phaseLabel(d.phase));
    pv('.xxdq-selector__pv--powerfactor', d.powerFactor.toFixed(2));
    pv('.xxdq-selector__pv--actual',   d.actualAmp + ' A');

    if (isPathA) {
      pv('.xxdq-selector__pv--power',        d.calculatedPower + ' kW');
      pv('.xxdq-selector__pv--target',       d.targetCurrent + ' A');
      // Path A: show warning strip when actualAmp < targetCurrent
      const warningEl = $('.xxdq-selector__warning', result);
      if (warningEl) {
        const warningText = warningEl.querySelector('.xxdq-selector__warning-text');
        if (warningText) warningText.textContent = d.warning || '';
        warningEl.classList.toggle('is-visible', !!d.warning);
      }
    } else {
      // Path B: back-calc from corrected ampacity
      pv('.xxdq-selector__pv--power',  LANG.backCalc);
      pv('.xxdq-selector__pv--target', '—');
      const warningEl = $('.xxdq-selector__warning', result);
      if (warningEl) hide(warningEl);
    }

    // ── Note strip (range upsell / DTL type note) ────────────────────────
    const noteEl  = $('.xxdq-selector__note', result);
    const noteText = $('.xxdq-selector__note-text', result);
    let note = '';
    if (!isPathA && !d.exact) {
      note = LANG.noteRounded(d.area, d.model, d.range[1]);
    } else if (d.type === 'DTL') {
      note = LANG.noteDtl;
    } else if (isPathA && !d.warning) {
      note = LANG.notePower(d.calculatedPower, d.voltage, phaseLabel(d.phase), d.targetCurrent, d.model);
    }
    if (noteEl && noteText) {
      noteText.textContent = note;
      if (noteEl.style) noteEl.style.display = note ? '' : 'none';
    }

    // ── Action buttons ─────────────────────────────────────────────────────
    const inquiryUrl = (root.dataset.inquiryUrl || '/inquiry/') + '?model=' + encodeURIComponent(d.model);
    const btn = $('.xxdq-selector__inquiry-btn', result);
    if (btn) btn.setAttribute('href', inquiryUrl);

    // "查看该型号产品" — only show if a product CPT entry exists for this model.
    const productBtn = $('.xxdq-selector__product-btn', result);
    if (productBtn) {
      let productMap = {};
      const mapEl = $('.xxdq-selector__product-map', root);
      if (mapEl && mapEl.textContent) {
        try { productMap = JSON.parse(mapEl.textContent); } catch (e) { productMap = {}; }
      }
      const productUrl = productMap[d.model];
      if (productUrl) {
        productBtn.setAttribute('href', productUrl);
        productBtn.style.display = '';
      } else {
        productBtn.style.display = 'none';
      }
    }

    show(result);
    result.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // ── Form binding ───────────────────────────────────────────────────────────
  function init(root) {
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    // Pick the string table from the root element's data-lang attribute.
    LANG = (root.dataset.lang === 'en') ? STRINGS.en : STRINGS.zh;

    // Populate area select from TERMINAL_DB
    const areaSel = $('#xxdq-area', root);
    if (areaSel) areaSel.innerHTML = areaOptionsHtml();

    // Default temp (spec §三)
    const tempInput = $('#xxdq-temp', root);
    if (tempInput && !tempInput.value) tempInput.value = 25;

    // Default powerFactor (spec §二)
    const pfInput = $('#xxdq-powerfactor', root);
    if (pfInput && !pfInput.value) pfInput.value = DEFAULT_POWER_FACTOR;

    // Submit
    const form = $('.xxdq-selector__form', root);
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const fd = new FormData(form);

      const area        = parseFloat(fd.get('area'));
      const material    = String(fd.get('material') || 'copper');
      const temp        = parseFloat(fd.get('temp') || '25');
      const install     = String(fd.get('install') || 'air');
      const type        = String(fd.get('type') || 'DT');
      const voltage     = parseFloat(fd.get('voltage') || '380');
      const phase       = String(fd.get('phase') || 'three');
      const power       = parseFloat(fd.get('power'));   // NaN when blank
      const powerFactor = parseFloat(fd.get('powerFactor') || String(DEFAULT_POWER_FACTOR));

      const payload = calculate({
        area:        area,
        material:    material,
        temp:        temp,
        install:     install,
        type:        type,
        voltage:     voltage,
        phase:       phase,
        power:       power,
        powerFactor: powerFactor,
      });
      renderResult(root, payload);
    });

    // Reset
    const resetBtn = $('.xxdq-selector__reset', root);
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        form.reset();
        if (tempInput) tempInput.value = 25;
        if (pfInput)  pfInput.value  = DEFAULT_POWER_FACTOR;
        hide($('.xxdq-selector__result', root));
        hide($('.xxdq-selector__error',  root));
        if (areaSel) areaSel.focus();
      });
    }

    // "重新计算" — scroll back to form
    const recalcBtn = $('.xxdq-selector__recalc-btn', root);
    if (recalcBtn) {
      recalcBtn.addEventListener('click', function (e) {
        e.preventDefault();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (areaSel) areaSel.focus();
      });
    }
  }

  function boot() {
    $all('.xxdq-selector').forEach(init);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
