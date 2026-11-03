<?php
/**
 * XXDQ i18n — create the English news articles.
 *
 * The Chinese news posts are long-form technical articles. They are
 * translated by hand here (rather than by the deterministic catalogue
 * translator, which only understands product titles and spec values),
 * because the English copy has to read like something a native
 * engineer would actually publish.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/xxdq-theme/tools/build-en-news.php
 *
 * The script is idempotent — running it again updates the existing
 * English posts instead of creating duplicates.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    $wp_root = dirname(dirname(dirname(dirname(__DIR__))));
    require_once $wp_root . '/wp-load.php';
}

if (!defined('ABSPATH')) {
    exit(1);
}

/**
 * Create or update the English counterpart of a Chinese news post.
 *
 * @param int    $zh_id   Chinese post ID.
 * @param array  $fields  post_title / post_name / post_content / post_excerpt.
 * @param string $path    Public English path, e.g. 'news/my-slug/'.
 * @return int English post ID.
 */
function xxdq_make_en_news($zh_id, $fields, $path) {
    $zh = get_post($zh_id);

    /* The English article carries the SAME publication date as the
     * Chinese one — they are the same piece of writing in two
     * languages, and a different date would break the chronological
     * ordering of the archive. */
    $date_fields = [];
    if ($zh) {
        $date_fields['post_date']     = $zh->post_date;
        $date_fields['post_date_gmt'] = $zh->post_date_gmt;
    }

    $existing = XXDQ_I18n_Content::get_en_post_id($zh_id);

    if ($existing) {
        wp_update_post(array_merge([
            'ID'           => $existing,
            'post_title'   => $fields['post_title'],
            'post_content' => $fields['post_content'],
            'post_excerpt' => $fields['post_excerpt'] ?? '',
            'post_status'  => 'publish',
        ], $date_fields));
        update_post_meta($existing, '_xxdq_en_slug', $fields['post_name']);
        update_post_meta($existing, '_xxdq_en_path', $path);
        return $existing;
    }

    $en_id = XXDQ_I18n_Content::create_en_post($zh_id, [
        'post_title'   => $fields['post_title'],
        'post_name'    => $fields['post_name'] . '-en',
        'post_content' => $fields['post_content'],
        'post_excerpt' => $fields['post_excerpt'] ?? '',
    ]);
    if (!$en_id) {
        return 0;
    }

    wp_update_post(array_merge([
        'ID'          => $en_id,
        'post_status' => 'publish',
    ], $date_fields));
    update_post_meta($en_id, '_xxdq_en_slug', $fields['post_name']);
    update_post_meta($en_id, '_xxdq_en_path', $path);

    return $en_id;
}

/* ══════════════════════════════════════════════════════════════
 * 1. ISO9001 recertification (Chinese post 999)
 * ══════════════════════════════════════════════════════════════ */

$iso_content = <<<'HTML'
<p>Yueqing Gengwen Electric has successfully passed its 2026 ISO9001 quality management system recertification. This is the tenth consecutive year the company has held the certification, confirming that its quality management standards remain at industry-leading levels.</p>
<h3>Audit Highlights</h3>
<ul>
<li>Incoming material procurement optimised: incoming inspection pass rate improved to 99.6%</li>
<li>Production process control: CPK for critical processes maintained above 1.33</li>
<li>Customer service system: complaint response time shortened to 4 hours</li>
</ul>
<p>The company will continue to hold "quality is the life of the enterprise" as its core principle and to supply high-quality electrical connection products to customers worldwide.</p>
HTML;

$en_iso = xxdq_make_en_news(999, [
    'post_title'   => 'Gengwen Electric Passes 2026 ISO9001 Quality Management System Recertification',
    'post_name'    => 'iso9001-2026-quality-management-recertification-passed',
    'post_content' => $iso_content,
    'post_excerpt' => 'Gengwen Electric has passed its 2026 ISO9001 recertification — the tenth consecutive year holding the certificate, with an incoming inspection pass rate of 99.6% and CPK above 1.33.',
], 'news/iso9001-2026-quality-management-recertification-passed/');

echo "EN news 999  -> {$en_iso}\n";

/* ══════════════════════════════════════════════════════════════
 * 2. 2026 new product launch (Chinese post 998)
 * ══════════════════════════════════════════════════════════════ */

$launch_content = <<<'HTML'
<p>On 21 September, Yueqing Gengwen Electric successfully held its 2026 new product launch at the Wenzhou headquarters. The event was themed "Smart Manufacturing of Connections · Quality Leads the Future" and showcased the new generation of copper lugs, crimp terminals and pre-insulated terminal series.</p>
<h3>Three Highlights of the New Range</h3>
<ul>
<li>Full range upgraded to T2 copper, improving conductive performance by 15%</li>
<li>Pre-insulated terminals certified by both UL and VDE</li>
<li>Crimp terminal range now covers the full 0.5–95 mm² span</li>
</ul>
<p>All products released have passed CE, RoHS and ISO9001 certification, and are widely applicable to switchgear, new energy, rail transit and industrial automation.</p>
HTML;

$en_launch = xxdq_make_en_news(998, [
    'post_title'   => 'Gengwen Electric Holds 2026 New Product Launch in Wenzhou',
    'post_name'    => 'gengwen-2026-new-product-launch-successful',
    'post_content' => $launch_content,
    'post_excerpt' => 'Gengwen Electric launched its 2026 product range in Wenzhou: the full copper lug series upgraded to T2 copper, UL and VDE certified pre-insulated terminals, and crimp terminals spanning 0.5–95 mm².',
], 'news/gengwen-2026-new-product-launch-successful/');

echo "EN news 998  -> {$en_launch}\n";

/* ══════════════════════════════════════════════════════════════
 * 3. DT copper lug selection guide (Chinese post 1585)
 * ══════════════════════════════════════════════════════════════ */

$dt_guide_content = <<<'HTML'
<!-- wp:paragraph -->
<p>The DT copper lug is the purple-copper connector terminal you see every day at the end of a cable inside a switchgear cabinet. The "DT" in the name is simply short for a copper connection terminal — D stands for crimping, T stands for copper. The full name is the DT series copper connection terminal, and it is one of the most fundamental — and least forgiving — parts of any electrical connection.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>It is normally extruded in one piece from T2 copper bar, with copper content above 99.9%. Solid and seamless, it conducts well, resists tensile stress, and does not deform easily after crimping. It looks insignificant, but in a high-current application it carries the connection reliability of the entire circuit.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The defining feature of the DT is that the tail is closed — the well-known "oil-block" design. When a cable termination is made, this closed tail prevents insulating oil from seeping out behind the terminal. There is a similar-looking sibling, the DTG, which is stamped from copper tube with an open tail and no oil blocking, and costs considerably less. So if you are building high-voltage cable terminations or working in an oil-immersed environment, never substitute a DTG for a DT — they are not interchangeable.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The model numbers are easy to read: the number is the cable cross-section. DT-70, for example, is for a 70 mm² copper cable. From DT-10 through DT-630 and DT-800 the range is essentially complete. As a rule, DT is used for copper cables of 10 mm² and above; below that a crimp ferrule is used. Genuine DT terminals must conform to GB/T 14315-2008, so it is worth checking for that when you buy.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Crimping is a craft and cannot be done with a single casual squeeze. The strip length has to match the barrel length so that the insulation butts right up against the terminal. The oxide layer on the copper strands is best sanded off, otherwise the contact resistance rises. In practice you crimp once near the tail of the terminal and then again near the root where it meets the busbar, using a matching hydraulic crimper and die so the conductor is fully enclosed. After crimping, fit a heat-shrink tube or wrap the insulation back with tape.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Two surface finishes are available. Plain copper is cheaper and suits dry indoor environments, but it will oxidise and darken over time. Tinned copper costs more but is used far more in damp, outdoor or photovoltaic applications, because the tin layer prevents copper oxidation and keeps the contact resistance stable.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Here are a few real-world situations that show you exactly where this part lives.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Switchgear incoming and outgoing feeders.</strong> This is DT's main battlefield. In a low-voltage switchgear cabinet the incoming cable comes from the transformer at 240 mm², 300 mm² or more. Once the cable is stripped, the copper core is pushed straight into the DT barrel and crimped solid with a hydraulic tool, and the round hole at the other end is bolted onto the breaker or busbar. Practically every main-circuit connection inside the cabinet relies on this part.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Box substations and transformers.</strong> Space inside a box substation is tight, and the cable termination between the transformer outgoing side and the low-voltage cabinet is almost always made on site. This is where the oil-block structure of the DT earns its place — especially with oil-immersed transformers, where the cable termination contains insulating oil. If the tail of the terminal is not sealed, the oil slowly seeps out and the insulation properties degrade over time. For transformer outgoing terminations, DT is the standard and DTG must not be used.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Photovoltaic DC side.</strong> With more and more photovoltaic plants being built, there are large volumes of PV-specific cable running from module strings to inverters and from inverters to combiner boxes. Most of these cables are copper or tinned copper, and the joints use tinned DT terminals. Outdoors, exposed to sun and rain, plain copper oxidises and turns black within months; the tin layer holds the process back and keeps the contact resistance stable. On photovoltaic projects, therefore, tinned DT is the default choice even though it costs more.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Industrial power cables.</strong> For factory motors, pumps and fans, the power cable runs from the switch room to the machine and is crimped with a DT at both ends. The currents are large and the vibration is heavy, so poor crimping quality leads to heating after a period of operation and in the worst case burns the terminal red. Industrial projects therefore impose stricter requirements on crimping: the hydraulic die must match the terminal size, and after crimping the joint has to be checked for off-centre or cracked deformation.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Cable termination construction.</strong> When making 10 kV or 35 kV cable terminations, the DT is the critical connection element. From stripping the outer sheath and armour through to crimping the DT terminal and restoring the insulation, every step has its own requirements. Here the DT does not merely conduct — it also carries the mechanical pull of the cable itself, so its tensile strength directly determines whether the termination keeps working reliably over the long term.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>EV charging and energy storage.</strong> EV chargers and battery storage cabinets also contain a large number of busbar and cable connections. The gun leads of charging piles and the battery-module connections inside storage cabinets frequently use DT terminals or similar crimped terminals. These applications combine high current with frequent mating and unmating, so they demand very high standards for conductive stability and vibration resistance.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ultimately, the DT copper lug is the "screw" of electrical connection — unremarkable in appearance, but indispensable in switchgear, transformers, photovoltaics, industry and charging infrastructure. Choose it well and crimp it properly, and it will work safely for decades; choose wrong or crimp it carelessly, and overheating, burning and outages are all its doing.</p>
<!-- /wp:paragraph -->
HTML;

$en_dt_guide = xxdq_make_en_news(1585, [
    'post_title'   => 'How to Select a DT Copper Lug Properly',
    'post_name'    => 'dt-copper-lug-selection-guide',
    'post_content' => $dt_guide_content,
    'post_excerpt' => 'What the DT series copper lug actually is, why the closed oil-block tail matters, and the six real applications where it does the heavy lifting — from switchgear feeders to EV charging.',
], 'news/dt-copper-lug-selection-guide/');

echo "EN news 1585 -> {$en_dt_guide}\n";

/* ══════════════════════════════════════════════════════════════
 * 4. Selecting a DT lug from load power (Chinese post 1588)
 * ══════════════════════════════════════════════════════════════ */

$power_guide_content = <<<'HTML'
<!-- wp:paragraph -->
<p>Choosing a DT copper lug is complicated only if you make it complicated. It is complicated because it involves converting voltage, current and power, plus the various demands of the installation environment. It is simple because it comes down to one sentence: <strong>look at the cable first, then at the current, and finally leave yourself some margin.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">🔌 Voltage, current, power — which one drives the selection?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Many people worry: "Mine is 380 V or 220 V — does a higher voltage mean a bigger lug?" In fact, DT lug selection <strong>depends on current alone</strong>. The voltage class mainly affects insulation and creepage distances; for a purely conductive bare terminal like a copper lug, as long as the voltage stays within the insulation rating of your equipment, the same cable can use the same lug at any of these voltages.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Power and current, on the other hand, do require a calculation. For a 10 kW three-phase 380 V motor the rated current is around 20 A (a rough rule of thumb: power × 2); a single-phase 220 V 10 kW load, by contrast, pulls about 45 A. <strong>So the first step is to establish the rated current of your equipment.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">📊 Quick selection table (just look it up)</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Below are the common DT sizes and their reference ampacity ratings. Note that these current values are safe reference ranges — they vary between standards and installation conditions — but they are enough for a first-pass selection.</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th class="has-text-align-left" data-align="left">Cable cross-section (mm²)</th><th class="has-text-align-left" data-align="left">DT model</th><th class="has-text-align-left" data-align="left">Reference ampacity (A)</th><th class="has-text-align-left" data-align="left">Typical use</th><th class="has-text-align-left" data-align="left">Stud hole reference</th></tr></thead><tbody><tr><td>10</td><td>DT-10</td><td>50–80</td><td>Low-power equipment, control panel wiring</td><td>M6/M8</td></tr><tr><td>16</td><td>DT-16</td><td>60–106</td><td>Lighting feeders, small motors</td><td>M6/M8</td></tr><tr><td>25</td><td>DT-25</td><td>80–143</td><td>Distribution box feeders, medium motors</td><td>M8/M10</td></tr><tr><td>35</td><td>DT-35</td><td>100–175</td><td>Workshop power lines, service drops</td><td>M8/M10</td></tr><tr><td>50</td><td>DT-50</td><td>125–223</td><td>Power supply for larger equipment</td><td>M10/M12</td></tr><tr><td>70</td><td>DT-70</td><td>160–265</td><td>Switchgear main circuits</td><td>M10/M12</td></tr><tr><td>95</td><td>DT-95</td><td>180–329</td><td>High-power equipment, main incomer</td><td>M12/M16</td></tr><tr><td>120</td><td>DT-120</td><td>250–382</td><td>Transformer outgoing, main runs</td><td>M12/M16</td></tr><tr><td>150</td><td>DT-150</td><td>~445</td><td>Large distribution systems</td><td>M12/M16</td></tr><tr><td>185</td><td>DT-185</td><td>315–519</td><td>Main distribution, high-current circuits</td><td>M16</td></tr><tr><td>240</td><td>DT-240</td><td>400–609</td><td>Main incomer, transformer side</td><td>M16/M20</td></tr><tr><td>300</td><td>DT-300</td><td>~705</td><td>High-capacity distribution</td><td>M16/M20</td></tr><tr><td>400</td><td>DT-400</td><td>~832</td><td>Substations, heavy industrial equipment</td><td>M16/M20</td></tr><tr><td>500</td><td>DT-500</td><td>~965</td><td>Heavy industry, large transformers</td><td>M16/M20</td></tr><tr><td>630</td><td>DT-630</td><td>~1134</td><td>Main transformers, very large equipment</td><td>M20</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p><em>Note: the ampacity figures reference recommended values from several sources; allow 15–20% margin in real applications.</em></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">📝 Selection in practice: three steps</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Step one: pick the model from the cable.</strong> The number in the DT model corresponds directly to the cable cross-section. If you are holding 35 mm², choose DT-35 — this is a hard rule. The internal diameter of the lug has to match the cable's outer diameter, and <strong>it should normally be 0.2–0.5 mm larger than the cable</strong>; too loose and it will not crimp tight, too tight and the cable will not go in.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Step two: pick the size from the current.</strong> Say you have calculated a rated current of 150 A. Looking at the table, DT-50 covers 125–223 A, so DT-50 is sufficient. But if the equipment runs at full load continuously, step up one size to DT-70 for extra margin.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Step three: leave margin.</strong> Manufacturer ratings are normally measured at an ambient temperature of 40 °C with a temperature rise of no more than 50 K. Your actual installation may be hotter, or several conductors may be bundled together with poor heat dissipation. In that case <strong>use the terminal at 80% of its rated current</strong> — in other words, apply a 20% derate. For 150 A, choose a size rated to carry 180 A or more.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">⚠️ Two common pitfalls</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Do not get the stud hole wrong.</strong> The number after DT is the wire size, but the stud hole in front of it has to match as well. If your busbar stud is M10, choose a lug with a hole of about 10.5 mm — <strong>a hole 0.2–0.5 mm larger than the stud is just right</strong>. Too small and it will not fit; too large and the contact area is insufficient, which leads to heating.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Match the material and plating to the environment.</strong> For ordinary environments, T2 copper with tin plating is sufficient — the tin prevents oxidation and keeps the contact resistance stable. But if your equipment is <strong>damp, oily or near the coast</strong>, the oil-block structure of the DT series (with a sealing ring on the inner wall) is the better choice, because it stops moisture and oil from tracking down the conductor into the barrel.</p>
<!-- /wp:paragraph -->
HTML;

$en_power_guide = xxdq_make_en_news(1588, [
    'post_title'   => 'How to Select a DT Copper Lug When All You Know Is the Load Power',
    'post_name'    => 'selecting-dt-copper-lug-from-load-power',
    'post_content' => $power_guide_content,
    'post_excerpt' => 'Voltage does not drive copper lug selection — current does. Here is the ampacity table, the three-step selection method and the two mistakes that cause most overheating.',
], 'news/selecting-dt-copper-lug-from-load-power/');

echo "EN news 1588 -> {$en_power_guide}\n";

/* ══════════════════════════════════════════════════════════════
 * 5. Why crimped terminals burn (Chinese post 1603)
 * ══════════════════════════════════════════════════════════════ */

$burn_content = <<<'HTML'
<!-- wp:paragraph -->
<p>The charred lump in your switchgear is nine times out of ten not a failed circuit breaker — it is a copper lug that was not crimped properly.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>In the language of electrical installation and maintenance, "copper lug" is an almost invisible term. It hangs off the end of a cable, hides inside a terminal block, and once the screw is tightened nobody thinks about it again. Yet this few-yuan copper tube is what decides whether a current of several hundred amperes can pass through safely. Every fraction of a milliohm added to the contact resistance piles up as Joule heat, until one day an infrared thermometer on a routine inspection lights up red — and by then it is usually too late.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>This article does not deal in generalities. It dissects the material logic of the copper lug, the make-or-break nature of crimping, and the inspection thresholds that are routinely overlooked.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">I. What it actually is: from copper bar to the current gate</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The technical name for a copper lug is "copper connection terminal". At heart it is a <strong>cross-section matcher</strong>. The cable conductor is a round bundle of twisted copper strands, whereas the connection hole on the equipment is usually a flat slot or a round stud hole. The job of the lug is to gather that loose bundle of strands into a rigid metal ring that a bolt can clamp down on.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Material is the first hard requirement. Genuine DT series copper lugs are made of <strong>T2 copper</strong>, with a copper content so high that conductivity approaches the theoretical value of pure copper. Brass is not used because the zinc in it markedly lowers conductivity, and in high-current applications that small difference in resistance is enough to make the terminal heat up. Sizes run from 10 mm² all the way to 630 mm², and the number corresponds directly to the conductor cross-section — it is not an arbitrary label.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Surface treatment is the dividing line. Bare copper exposed to air darkens within days, and copper oxide has a far higher resistance than pure copper. That is why almost every serious copper lug is <strong>tinned</strong>. The silver-white tin layer keeps air out and, as a bonus, acts as a solid lubricant during crimping so that the contact between the copper barrel and the copper strands is denser. If the lug you receive is raw red copper with no pickling or passivation, toss it into a damp distribution cabinet and chances are it will be green when you open the cabinet six months later.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">II. Crimping: where every accident starts</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Buying the right copper lug only gets you half way. <strong>Crimping is the part that decides success or failure.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The industry has a rule of thumb: a joint only counts as sound if its contact resistance is no more than 1.1 times the resistance of a conductor of the same length. What guarantees that? The crimping tool forcing the barrel and the strands to <strong>cold-weld</strong> into one solid body. Between the strands, and between the strands and the inner wall of the barrel, the metal must be squeezed into atomic-level contact, with every trace of air driven out.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The most common cause of failures in the field is precisely "close enough". A squeeze with combination pliers, or a crimp die two sizes too large, flattens the barrel while leaving the strands inside loose. Once current flows, the poor contact point generates local hot spots, the tin layer melts, the copper surface oxidises faster and the resistance climbs again — a vicious cycle. The end result is either a terminal burnt through or cable insulation cooked off, followed by a short and a trip.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The correct method is boring and strict: the strip length has to be such that the strands <strong>fully enter the barrel without protruding past the edge</strong>, the crimp die must match the lug size exactly, the crimping proceeds in steps from the barrel mouth towards the stud end, and afterwards you pull the cable by hand — if the lug does not move at all, the joint passes. For multi-stranded fine-wire cables, if strands fan out of the barrel mouth after crimping, you have effectively buried a time bomb at the root of the terminal.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>There is also an argument that electricians never settle: <strong>tin first or crimp first?</strong> From the point of view of the inspection standards, a tinned lug already carries an anti-corrosion layer, so if the crimp is sound no additional tinning is needed. Extra high-temperature tinning can actually anneal and soften an already-crimped barrel, reducing the contact pressure. If you must tin, then <strong>tin the conductor first and crimp afterwards</strong>, so the tin fills the gaps between the strands rather than heating the finished terminal.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">III. The inspection threshold: temperature rise does not lie</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>With the lug installed, how do you know whether to trust it? Visual inspection only tells you so much. The real judge is the <strong>temperature-rise test</strong>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>GB/T 2314 requires that, once the rated current has been applied and the joint has stabilised, the terminal temperature rise shall not exceed <strong>45 K</strong> above ambient. In other words, if the ambient temperature inside the switchgear is 40 °C and the surface of the lug is above 85 °C, the connection is already on the edge of failure. An infrared camera sweep across the terminal block shows up the "hot spots" that sit ten-odd degrees above their neighbours — those are the signals to shut down, re-tighten or replace.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Quantifying contact resistance relies on a micro-ohmmeter. A sound crimp should read in the micro-ohm range, essentially indistinguishable from a conductor of the same length. If the measured value is clearly high, do not hesitate — cut it off and crimp again. Copper lugs are cheap; a burnt cabinet is not.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">IV. The silent rule of selection</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>One last word on selection. Many people think a copper lug just has to be "roughly the right size" and will force a 16 mm² lug onto 10 mm² cable. That is wrong. If the internal diameter does not match the conductor cross-section, then after crimping either the strands are not fully enclosed or a cavity is left between the barrel wall and the strands. <strong>A cavity is a perfect incubator for oxidation and overheating.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Then there is the choice between types. DT is the closed oil-block type, suitable for outdoor or damp environments because oil and moisture cannot get into the barrel; SC is the inspection-hole type, which lets the installer see whether the strands are fully seated and is therefore easier to work with, but offers slightly less protection in harsh conditions. Do not use an SC in place of a DT, and do not settle for thin-wall open-barrel terminals in high-vibration applications such as marine or vehicle installations.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Nobody gives a copper lug a second glance once it is inside the cabinet. But electricity is never sentimental: the thirty seconds you saved at the crimping tool will be charged back with interest on some hot summer night at full load. <strong>Push the strands all the way in, use the right die, crimp hard, and measure the temperature difference</strong> — do those four things and the copper tube will be worthy of every ampere it carries.</p>
<!-- /wp:paragraph -->
HTML;

$en_burn = xxdq_make_en_news(1603, [
    'post_title'   => 'Copper Lugs: The "Life-or-Death Joint" in Your Cable, and Why Your Crimped Terminals Keep Burning',
    'post_name'    => 'why-crimped-copper-lugs-burn',
    'post_content' => $burn_content,
    'post_excerpt' => 'The char mark in your switchgear is rarely the breaker — it is the crimp. A look at copper lug material, the 45 K temperature-rise limit, contact resistance and the mistakes that start fires.',
], 'news/why-crimped-copper-lugs-burn/');

echo "EN news 1603 -> {$en_burn}\n";

/* ══════════════════════════════════════════════════════════════
 * 6. Crimp terminal standards (Chinese post 1606)
 * ══════════════════════════════════════════════════════════════ */

$standards_content = <<<'HTML'
<!-- wp:paragraph -->
<p><strong>What does "crimped correctly" actually mean?</strong> By feel, by the experience of a master electrician, by "it looks about right" — against a real crimping standard, none of that counts for anything.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The crimp quality of a cold-pressed terminal has never been a vague sense of workmanship. It is a set of hard figures that can be measured, judged and rejected. The problem is that most people have never opened the standard in the first place.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">I. First: which standard should you be reading?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The standards system for cold-pressed terminals is fragmented — different industries and different scenarios cite entirely different specifications. The table below lays out the main standard systems, their applicable scenarios and their core focus:</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th class="has-text-align-left" data-align="left">Standard system</th><th class="has-text-align-left" data-align="left">Applicable scenario</th><th class="has-text-align-left" data-align="left">Core focus</th><th class="has-text-align-left" data-align="left">Key figures / characteristics</th></tr></thead><tbody><tr><td>JB/T 2436.1 / 2436.2</td><td>Electrical assemblies, power distribution</td><td>Technical requirements for the terminal product itself</td><td>Split into 0.5–6.0 mm² and 10–300 mm² bands</td></tr><tr><td>T/ACCEM group standard</td><td>General cold-pressed connection terminals</td><td>Electrical performance and thermal cycling</td><td>Temperature rise ≤ 30 K; thermal cycle 125: temperature-rise increment ≤ 8 K</td></tr><tr><td>USCAR-21</td><td>Automotive wiring harnesses (US)</td><td>Crimp height tolerance and compression ratio</td><td>Crimp height tolerance typically ±0.03 mm; compression ratio 15–20%</td></tr><tr><td>VW 60330</td><td>Automotive wiring harnesses (DE)</td><td>Cross-section zones and gas tightness</td><td>A/B/C zone division; compression ratio 10–20%</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p><strong>Key insight: no single "general crimping standard" covers every scenario.</strong> Applying the 30 K temperature rise of power fittings to the crimp height of an automotive terminal, or the reverse, is measuring with the wrong ruler. The logic of a DT terminal in a distribution cabinet is "carry the current without overheating"; the logic of an automotive harness terminal is "do not loosen under vibration, do not corrode, stay gas-tight". The two share the cold-weld principle, but their acceptance criteria are entirely different.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">II. The figures the standards really insist on</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Open the T/ACCEM standard for cold-pressed connection terminals and there is one easily overlooked but critical provision: <strong>the temperature rise at the crimped section shall not exceed 30 K</strong>. Note that this is stricter than the 45 K or 50 K that many test institutions habitually quote. What does 30 K mean in practice? At an ambient temperature of 40 °C, the terminal surface must not exceed 70 °C. Above that line the terminal's oxidation rate enters the accelerated range.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>But temperature rise is only an outcome metric. What the standard is really guarding against is <strong>process loss of control</strong>. T/ACCEM explicitly requires that the crimping tool be certified by a professional body, and that the jaw, the conductor and the terminal size match. Translated into field language: <strong>you may not carry one tool for every job, and you may not use a die two sizes too large on a small terminal.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Thermal cycling brings the time dimension into it. The table below sets out the differences in T/ACCEM thermal cycle requirements across terminal cross-section bands:</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th class="has-text-align-left" data-align="left">Cross-section band</th><th class="has-text-align-left" data-align="left">Cycle 25 requirement</th><th class="has-text-align-left" data-align="left">Cycle 125 requirement</th><th class="has-text-align-left" data-align="left">Long-term criterion</th></tr></thead><tbody><tr><td>0.5–6.0 mm²</td><td>Temperature rise at crimp ≤ temperature rise at 0.5 m from the conductor</td><td>Temperature rise ≤ cycle-25 temperature rise + 8 K</td><td>Temperature-rise increment control</td></tr><tr><td>10–300 mm²</td><td>Temperature rise at crimp ≤ temperature rise at 0.5 m from the conductor + 8 K</td><td>—</td><td>Resistance after cycle 125 ≤ 1.5× the pre-test value</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>The criterion for small terminals focuses on "the temperature rise does not climb"; large terminals add "the resistance does not double". Both paths point at the same enemy: a crimp whose cold weld is not sound gradually loosens under repeated thermal expansion and contraction, and the contact resistance creeps upward. At cycle 25 it still looks acceptable; by cycle 125 the truth is out.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">III. The silent language of the crimp cross-section</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The standard text will not tell you what a good crimp should look like, but the process manuals and the German standards will. VW 60330 divides the crimp cross-section explicitly into three functional zones, which makes the judgement logic extremely clear:</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th class="has-text-align-left" data-align="left">Zone</th><th class="has-text-align-left" data-align="left">Name</th><th class="has-text-align-left" data-align="left">Function</th><th class="has-text-align-left" data-align="left">Judgement criteria</th></tr></thead><tbody><tr><td>Zone A</td><td>Conductor crimp zone</td><td>Gas-tight cold weld between strands and terminal</td><td>Strands form a dense honeycomb with no continuous gaps</td></tr><tr><td>Zone B</td><td>Insulation crimp zone</td><td>Stress relief, prevents force reaching Zone A</td><td>Insulation is reliably gripped and does not slip</td></tr><tr><td>Zone C</td><td>Transition zone</td><td>Observes whether material flow is reasonable</td><td>No cracks, no abnormal stress concentration</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>The "honeycomb structure" of Zone A is the core criterion. If you can still see obvious continuous gaps between the strands on the cross-section, the strands have merely been "pushed together" and gas tightness has not been achieved. Gas-tight means not even gas molecules can pass, which leaves oxidation and corrosion with nowhere to start.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Beyond the zoning, a set of geometric parameters forms the "pass file" for a crimp cross-section. The table below summarises the thresholds commonly used to judge a general crimp cross-section:</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th class="has-text-align-left" data-align="left">Parameter</th><th class="has-text-align-left" data-align="left">Symbol</th><th class="has-text-align-left" data-align="left">Pass criterion</th><th class="has-text-align-left" data-align="left">Meaning</th></tr></thead><tbody><tr><td>Support angle</td><td>αw</td><td>≤ 30° (deviation from vertical)</td><td>Whether the crimp wings effectively enclose the conductor</td></tr><tr><td>Support height</td><td>La</td><td>≥ 1×S (S = terminal material thickness)</td><td>Whether the crimp wing ends support each other</td></tr><tr><td>Crimp wing end gap</td><td>Fa</td><td>≥ 0.1×S</td><td>The wing ends must not touch the inner barrel wall</td></tr><tr><td>Burr height</td><td>Fh</td><td>≤ 1×S</td><td>Die wear and clearance control</td></tr><tr><td>Base thickness</td><td>Sb</td><td>≥ 0.75×S</td><td><strong>The first red line against over-compression</strong></td></tr><tr><td>Compression ratio</td><td>—</td><td>approx. 10–20% (VW/USCAR)</td><td>Densification of the copper strands</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>Base thickness Sb ≥ 0.75S deserves a word of its own. It is the prerequisite indicator for judging <strong>over-compression</strong>: if the base of the terminal has been squeezed too thin, the force has exceeded what the material can reasonably withstand and the strands may already be damaged or even broken. The resistance reading may still look fine in the short term, but strand fracture in a vibration environment is only a matter of time.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">IV. Where automotive's "absurd tolerances" come from</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you are working in switchgear, a crimp height that is 0.1 mm out may go unremarked. In the automotive harness industry, however, the crimp height tolerance is often <strong>±0.03 mm</strong>, relaxed to ±0.05 mm or ±0.1 mm for some sizes depending on the terminal and conductor diameter.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Why so narrow? Because crimp height is fundamentally a <strong>proxy variable for the compression ratio</strong>. When the copper strands are compressed by 15–30% the cold weld is at its best; under-compression leaves air between the strands and raises the contact resistance; over-compression damages the strands and costs mechanical strength. On a terminal with a typical crimp height of 1.20 mm, a variation of ±0.05 mm is enough to push the compression ratio out of the acceptable window.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The table below summarises the default tolerances of VW 60330 for each crimp height band (where the drawing does not state otherwise):</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th class="has-text-align-left" data-align="left">Crimp height range</th><th class="has-text-align-left" data-align="left">Default tolerance</th></tr></thead><tbody><tr><td>0.22–0.5 mm</td><td>±0.03 mm</td></tr><tr><td>0.5–2.5 mm</td><td>±0.05 mm</td></tr><tr><td>&gt;2.5 mm</td><td>±0.1 mm</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>Source: VW 60330 crimp height tolerance provisions</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Note that this does not mean terminals in switchgear can be crimped any old way. It means that the acceptance logic of that system does not revolve around crimp height but makes its final judgement on <strong>temperature rise and contact resistance</strong>. The automotive industry moves the control point forward to crimp height because harness output is high and the takt time is fast: a parameter that can be measured online and non-destructively has to be used to lock the process window in real time.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">V. The half the standards cannot govern</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Standards can specify the temperature-rise limit, the die matching and the cross-section parameters. They cannot specify one thing: <strong>whether the operator actually pushed the strands all the way in.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>If the strip length is too short and the strands only enter halfway, the crimping tool will still produce a perfectly hexagonal-looking crimp. A temperature-rise test may just about pass in the laboratory, but installed on site and running at full load through a summer, the current finds too few metal contact points in the void at the back of the barrel, and the heat accumulates there.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>So the true position of the standards is this: <strong>they are a pass mark, not a certificate of excellence.</strong> Clearing the line only shows that the crimp will not fail immediately under test conditions. What decides whether it is still there in ten years is whether the strands were pushed fully home at the moment of crimping, whether the die closed completely, and whether the jaws were replaced when they should have been.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>One practical rule replaces a thousand words: <strong>pull on it after crimping.</strong> Grip the lug and twist the cable hard. If there is any relative movement between the lug and the insulation, or if the strands back out of the barrel mouth, the crimp has failed. Temperature rise and contact resistance are post-hoc tests; this pull is the on-the-spot verdict. However thick the standard book, what ends up in your hand is this one pull.</p>
<!-- /wp:paragraph -->
HTML;

$en_standards = xxdq_make_en_news(1606, [
    'post_title'   => 'Crimp Terminal Standards: The Handbook Nobody Reads That Decides Whether Your Terminal Will Burn',
    'post_name'    => 'crimp-terminal-crimping-standards',
    'post_content' => $standards_content,
    'post_excerpt' => 'JB/T 2436, T/ACCEM, USCAR-21 and VW 60330 — what each standard actually requires, the 30 K and 45 K temperature-rise limits, the three crimp cross-section zones, and the ±0.03 mm automotive tolerance.',
], 'news/crimp-terminal-crimping-standards/');

echo "EN news 1606 -> {$en_standards}\n";

echo "Done.\n";
