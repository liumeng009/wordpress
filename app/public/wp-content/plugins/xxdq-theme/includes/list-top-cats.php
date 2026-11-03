<?php
// Eval'd via WordPress admin-ajax or wp-cli substitute — actually we'll call
// wp eval-file via http request to /wp-admin/admin-ajax.php?action=...

// Simpler: write this to a temp .php file in the plugin dir and load it via
// direct PHP eval via admin-ajax?action=xxdq_list_top_cats (registered below).

add_action('wp_ajax_xxdq_list_top_cats', function() {
    if (!current_user_can('manage_options')) { wp_send_json_error('forbidden'); }
    $cats = get_terms(['taxonomy'=>'product_category','parent'=>0,'hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
    $out = [];
    foreach ($cats as $c) {
        $out[] = [
            'term_id' => $c->term_id,
            'name'    => $c->name,
            'slug'    => $c->slug,
            'count'   => $c->count,
            'desc'    => $c->description,
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    wp_die();
});
