<?php
// Modèle de la fiche produit
get_header();

// Vérifiez si Advanced Custom Fields est actif
if (function_exists('get_field')) {
    // Récupérez l'ID du produit
    $product_id = get_the_ID();

    // Récupérez les données personnalisées avec Advanced Custom Fields
    $custom_field_value = get_field('custom_field_name', $product_id);

    // Affichez les données du produit
    echo '<h1>' . get_the_title() . '</h1>';
    echo '<p>Custom Field: ' . esc_html($custom_field_value) . '</p>';

    // Ajoutez d'autres éléments ou personnalisez selon vos besoins

} else {
    // Affichez un message si Advanced Custom Fields n'est pas activé
    echo '<p>Advanced Custom Fields doit être activé pour afficher les données personnalisées.</p>';
}

get_footer();

