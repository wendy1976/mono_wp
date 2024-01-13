<?php
set_time_limit(300);
/*
Plugin Name: Open Food Facts Module
Description: Module pour la mise à jour des produits depuis Open Food Facts.
Version: 1.0
Author: Caroline Ferru
*/

// Activation du plugin : crée la table personnalisée lors de l'activation
register_activation_hook(__FILE__, 'creer_table_produits');

// Désactivation du plugin : supprime la table lors de la désactivation
register_deactivation_hook(__FILE__, 'supprimer_table_produits');

// Fonction pour créer la table lors de l'activation
function creer_table_produits() {
    global $wpdb;

    $nom_table = $wpdb->prefix . 'produits'; // Ajoute le préfixe de la base de données WordPress

    $sql = "CREATE TABLE $nom_table (
        id INT NOT NULL AUTO_INCREMENT,
        id_produit VARCHAR(255) NOT NULL,
        reference_produit VARCHAR(255),
        nom_produit VARCHAR(255),  -- Ajout de la colonne nom_produit
        description_produit TEXT,
        image_produit VARCHAR(255),
        mots_cles TEXT,
        note_nutriscore VARCHAR(10),
        energie INT,  -- Utilisez INT pour un nombre entier
        date_derniere_maj TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    )";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Fonction pour supprimer la table lors de la désactivation
function supprimer_table_produits() {
    global $wpdb;

    $nom_table = $wpdb->prefix . 'produits';
    $wpdb->query("DROP TABLE IF EXISTS $nom_table");
}

// Fonction pour insérer un produit dans la base de données
function inserer_produit_dans_bdd($code_barre) {
    global $wpdb;
    $nom_table = $wpdb->prefix . 'produits';

    $url_api = "https://world.openfoodfacts.org/api/v0/product/{$code_barre}.json";
    $response = wp_remote_get($url_api);

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['product'])) {
            $produit = $data['product'];

            $id_produit = $produit['_id'];
            $reference_produit = $produit['code'];

            // Vérifie si le produit existe déjà dans la base de données
            $produit_existant = $wpdb->get_row("SELECT * FROM $nom_table WHERE id_produit = '$id_produit'");

            if ($produit_existant === null) {
                // Le produit n'existe pas, donc on l'insère
                $nom_produit = isset($produit['product_name_fr']) ? $produit['product_name_fr'] : '';
                $description_produit = isset($produit['ingredients_text_fr']) ? $produit['ingredients_text_fr'] : '';
                $image_produit = $produit['image_url'];
                $mots_cles = isset($produit['categories']) ? $produit['categories'] : '';
                $note_nutriscore = isset($produit['nutriscore_grade']) ? $produit['nutriscore_grade'] : '';
                $energie = isset($produit['nutriments']['energy-kcal_100g']) ? $produit['nutriments']['energy-kcal_100g'] : '';

                // Insérer dans la table personnalisée
                $wpdb->insert(
                    $nom_table,
                    array(
                        'id_produit' => $id_produit,
                        'reference_produit' => $reference_produit,
                        'nom_produit' => $nom_produit,
                        'description_produit' => $description_produit,
                        'image_produit' => $image_produit,
                        'mots_cles' => $mots_cles,
                        'note_nutriscore' => $note_nutriscore,
                        'energie' => $energie,
                        'date_derniere_maj' => current_time('mysql', 1), // Met à jour la date actuelle
                    )
                );
            }
        }
    }
}

// Fonction d'initialisation pour insérer des produits lors du chargement
function inserer_produits_au_demarrage() {
    // Vérifie si les produits ont déjà été insérés
    if (get_option('produits_inseres') !== 'oui') {
        $codes_barres = array('20023775', '3166359678103', '3175681210981', '3302741843104', '3175681072763', '3248830692287', '3266140060176', '3041091339911', '3041091339645', '3021690029093', '3240931545356',
        '3322680010405', '3242272371052', '3700679600194', '3038359003219', '3083681070491', '3038350208613', '3487400004390', '3270160891382', '3270160890163', '8720182108937', '3289943400611', '7613039972083', '3483463020247', '3289476000012', '3266140061548', '3175681061491', '3250391110483', '3252970009881', '3256220030458', '3256220071482', '3256224059752', '3266140060152', '7613035768154', '3256221976694', '3368955060294', '3560070818334', '3560070921942', '3564700653395', '3564709015484', '3021690010985', '3176580101349', '3248830227236', '3250391885244', '3256226758387', '3560070606900', '3256228125552', '3222473660568');

        foreach ($codes_barres as $code_barre) {
            inserer_produit_dans_bdd($code_barre);
        }

        // Marque les produits comme insérés
        update_option('produits_inseres', 'oui');
    }
}

register_activation_hook(__FILE__, 'inserer_produits_au_demarrage');

//Interface 1 : Affichage des détails étendus d'un produit
function afficher_details_etendus_produit_shortcode($atts) {
    global $post, $wpdb;

    // Traitement de la recherche par référence
    if (isset($_GET['reference_produit'])) {
        $reference_produit = sanitize_text_field($_GET['reference_produit']);
        $produit = get_produit_par_reference($reference_produit);

        if ($produit) {
            // Afficher la fiche produit avec les options de modification
            $output = '<div class="details-etendus-produit">';
            $output .= '<h2>Détails étendus du produit : ' . $produit->nom_produit . '</h2>';
            $output .= '<img src="' . $produit->image_produit . '" alt="' . $produit->nom_produit . '">';
            // Ajouter d'autres détails du produit ici
            $output .= '<p><strong>Référence :</strong> ' . $produit->reference_produit . '</p>';
            $output .= '<p><strong>Mots-clés :</strong> ' . $produit->mots_cles . '</p>';
            $output .= '<p><strong>Nutriscore :</strong> ' . $produit->note_nutriscore . '</p>';
            $output .= '<p><strong>Énergie :</strong> ' . $produit->energie . ' Kcal pour 100g</p>';
            $output .= '<p><strong>Description :</strong> ' . $produit->description_produit . '</p>';
            $output .= '<p><strong>Date de mise à jour :</strong> ' . $produit->date_derniere_maj . '</p>';

            // Afficher le message de confirmation
            $confirmation_message = isset($_GET['confirmation']) ? urldecode($_GET['confirmation']) : '';
            if (!empty($confirmation_message)) {
                $output .= '<p class="confirmation-message">' . esc_html($confirmation_message) . '</p>';
            }

            // Formulaire de modification du produit
            $output .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
            $output .= '<input type="hidden" name="action" value="modifier_produit">';
            $output .= '<input type="hidden" name="produit_id" value="' . esc_attr($produit->id) . '">';

            // Champs de modification
            $output .= '<label for="nouveau_nom_produit">Nouveau nom :</label>';
            $output .= '<input type="text" name="nouveau_nom_produit" id="nouveau_nom_produit" placeholder="Nouveau nom" value="' . esc_attr($produit->nom_produit) . '">';
            
            $output .= '<label for="nouvelle_image_produit">Nouvelle image :</label>';
            $output .= '<input type="file" name="nouvelle_image_produit" id="nouvelle_image_produit">';

            $output .= '<label for="nouveaux_mots_cles">Nouveaux mots-clés :</label>';
            $output .= '<input type="text" name="nouveaux_mots_cles" id="nouveaux_mots_cles" placeholder="Nouveaux mots-clés" value="' . esc_attr($produit->mots_cles) . '">';
            
            $output .= '<label for="nouvelle_description_produit">Nouvelle description :</label>';
            $output .= '<textarea name="nouvelle_description_produit" id="nouvelle_description_produit" placeholder="Nouvelle description">' . esc_textarea($produit->description_produit) . '</textarea>';

            $output .= '<label for="nouveau_nutriscore">Nouveau nutriscore :</label>';
            $output .= '<input type="text" name="nouveau_nutriscore" id="nouveau_nutriscore" placeholder="Nouveau nutriscore" value="' . esc_attr($produit->note_nutriscore) . '">';
            
            $output .= '<label for="nouvelle_energie">Nouvelle énergie :</label>';
            $output .= '<input type="text" name="nouvelle_energie" id="nouvelle_energie" placeholder="Nouvelle énergie" value="' . esc_attr($produit->energie) . '">';
            
            $output .= '<input type="submit" value="Modifier">';
            $output .= '</form>';

            $output .= '</div>';
        } else {
            $output = '<p>Aucun produit trouvé avec la référence : ' . esc_html($reference_produit) . '</p>';
        }

        return $output;
    }

    // Affichage du formulaire de recherche par référence
    $output = '<div class="details-etendus-produit">';
    $output .= '<form method="get" action="' . esc_url(get_permalink()) . '">';
    $output .= '<label for="reference_produit">Référence du produit :</label>';
    $output .= '<input type="text" name="reference_produit" id="reference_produit" value="' . esc_attr($_GET['reference_produit'] ?? '') . '">';
    $output .= '<input type="submit" value="Rechercher">';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}
add_shortcode('afficher_details_etendus_produit', 'afficher_details_etendus_produit_shortcode');

// Fonction pour récupérer un produit par référence
function get_produit_par_reference($reference_produit) {
    global $wpdb;
    $nom_table = $wpdb->prefix . 'produits';

    $produit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $nom_table WHERE reference_produit = %s", $reference_produit));

    return $produit;
}


// Ajoutez cette action pour gérer la modification du produit
add_action('admin_post_modifier_produit', 'modifier_produit');
add_action('admin_post_nopriv_modifier_produit', 'modifier_produit');

// Fonction pour modifier le produit dans la base de données
function modifier_produit() {
    global $wpdb;

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'modifier_produit') {
        $produit_id = intval($_POST['produit_id']);
        $nouveau_nom_produit = sanitize_text_field($_POST['nouveau_nom_produit']);
        $nouvelle_image_produit = $_FILES['nouvelle_image_produit'];
        $nouveaux_mots_cles = sanitize_text_field($_POST['nouveaux_mots_cles']);
        $nouvelle_description_produit = sanitize_text_field($_POST['nouvelle_description_produit']);
        $nouveau_nutriscore = sanitize_text_field($_POST['nouveau_nutriscore']);
        $nouvelle_energie = intval($_POST['nouvelle_energie']);

        // Mettre à jour les champs dans la base de données
        $donnees_update = array('nom_produit' => $nouveau_nom_produit, 'mots_cles' => $nouveaux_mots_cles, 'description_produit' => $nouvelle_description_produit, 'note_nutriscore' => $nouveau_nutriscore, 'energie' => $nouvelle_energie);

        // Mettre à jour l'image du produit si elle est fournie
        if ($nouvelle_image_produit['name']) {
            $upload = wp_upload_bits($nouvelle_image_produit['name'], null, file_get_contents($nouvelle_image_produit['tmp_name']));
            if (isset($upload['error']) && $upload['error'] !== false) {
                wp_die('Erreur lors du téléchargement de l\'image');
            } else {
                $donnees_update['image_produit'] = $upload['url'];
            }
        }

        $wpdb->update(
            $wpdb->prefix . 'produits',
            $donnees_update,
            array('id' => $produit_id)
        );

        // Rediriger l'utilisateur vers la page de confirmation après la modification
        $confirmation_page_slug = 'confirmation-modification-produit'; // Remplacez par le slug de votre page de confirmation
        $redirect_url = get_permalink(get_page_by_path($confirmation_page_slug)->ID);
        wp_redirect($redirect_url);
        exit;
    }
}


// Interface 2 : Listing de produits sous forme de tableau
function afficher_produits_shortcode($atts) {
    global $wpdb;
    $nom_table = $wpdb->prefix . 'produits';

    // Ajoutez un message de débogage
    error_log('Début de la fonction afficher_produits_shortcode');

    // Traitement des actions
    traiter_actions_produits();

    $output = '';

    // Formulaire d'ajout
    $output .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
    $output .= '<input type="hidden" name="action" value="ajouter_produit">';
    $output .= '<label for="code_barre">Code-barres Open Food Facts :</label>';
    $output .= '<input type="text" name="code_barre" id="code_barre" placeholder="Code-barres">';
    $output .= '<input type="submit" value="Ajouter un nouveau produit">';
    $output .= '</form>';

    // Récupérer tous les produits de la table
    $produits = $wpdb->get_results("SELECT * FROM $nom_table");

    // Tableau pour afficher les produits
    $output .= '<table class="liste-produits">';
    $output .= '<thead>';
    $output .= '<tr>';
    $output .= '<th>Image</th>';
    $output .= '<th>Référence</th>';
    $output .= '<th>Nom</th>';
    $output .= '<th>Supprimer</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';

    // Boucle pour afficher chaque produit dans une ligne du tableau
    foreach ($produits as $produit) {
        $output .= '<tr>';
        $output .= '<td><img src="' . $produit->image_produit . '" alt="' . $produit->description_produit . '" style="width: 50px;"></td>';
        $output .= '<td>' . $produit->reference_produit . '</td>';
        $output .= '<td>' . $produit->nom_produit . '</td>';

        // Formulaire de suppression
        $output .= '<td>';
        $output .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $output .= '<input type="hidden" name="action" value="supprimer_produit">';
        $output .= '<input type="hidden" name="produit_id" value="' . esc_attr($produit->id) . '">';
        $output .= '<input type="submit" value="Supprimer">';
        $output .= '</form>';
        $output .= '</td>';

        $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';

    // Ajoutez un message de débogage
    error_log('Fin de la fonction afficher_produits_shortcode');

    return $output;
}
add_shortcode('afficher_produits', 'afficher_produits_shortcode');


// Fonction pour récupérer les détails du produit par ID
function get_produit_par_id($produit_id) {
    global $wpdb;
    $nom_table = $wpdb->prefix . 'produits';

    return $wpdb->get_row("SELECT * FROM $nom_table WHERE id = $produit_id");
}


// Gestion des actions côté serveur
function traiter_actions_produits() {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        switch ($action) {
            case 'modifier_produit':
                $produit_id = $_POST['produit_id'];
                $nouveau_nom_produit = $_POST['nouveau_nom_produit'];

                // Ajoutez un message de débogage
                error_log('Début de la modification de produit. ID: ' . $produit_id);

                // Code pour mettre à jour le nom du produit dans la base de données
                global $wpdb;
                $nom_table = $wpdb->prefix . 'produits';
                $wpdb->update(
                    $nom_table,
                    array('nom_produit' => $nouveau_nom_produit),
                    array('id' => $produit_id)
                );

                // Ajoutez un message de débogage
                error_log('Fin de la modification de produit.');

                break;

            case 'ajouter_produit':
                $code_barre = sanitize_text_field($_POST['code_barre']);

                // Ajoutez un message de débogage
                error_log('Ajout d\'un nouveau produit. Code-barres: ' . $code_barre);

                inserer_produit_dans_bdd($code_barre);
                break;

            case 'supprimer_produit':
                $produit_id = $_POST['produit_id'];

                // Ajoutez un message de débogage
                error_log('Suppression d\'un produit. ID: ' . $produit_id);

                // Code pour supprimer le produit de la base de données
                global $wpdb;
                $nom_table = $wpdb->prefix . 'produits';
                $wpdb->delete(
                    $nom_table,
                    array('id' => $produit_id)
                );

                // Ajoutez un message de débogage
                error_log('Fin de la suppression de produit.');

                break;

            // Ajoutez d'autres cas pour d'autres actions si nécessaire

            default:
                // Action non reconnue
                break;
        }
    }
}

add_action('admin_post_nopriv', 'traiter_actions_produits');
add_action('admin_post', 'traiter_actions_produits');

