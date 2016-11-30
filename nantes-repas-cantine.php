<?php
/**
 * Plugin Name: Nantes cantines
 * Description: Ajout d'une widget qui affiche les repas de la semaine dans les cantines de Nantes
 * Author: Arnaud Banvillet
 * Version: 1.0
 * Author URI: http://arnaudban.me
 */


class Nantes_Repas_Cantine extends WP_Widget {

    /**
     * Sets up the widgets name etc
     */
    public function __construct() {
        $widget_ops = array(
            'classname' => 'nantes-repas-cantine',
            'description' => 'Affiche les repas de la semaine pour les écoles de nantes',
        );
        parent::__construct( 'nantes_repas_cantine', 'Nantes cantines', $widget_ops );
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function widget( $args, $instance ) {

        // On récupére la date du lundi et du vendredi de la semaine
        // next Monday 2012-04-01
        $ask_date = isset( $_GET['catine-week'] ) ? $_GET['catine-week'] : false;

        if( $ask_date ){

            try {

                $monday = new DateTime( 'monday this week ' . $ask_date );

            } catch (Exception $e) {

                $monday = new DateTime( 'monday this week' );
            }

        } else {

            $monday = new DateTime( 'monday this week' );
        }


        $monday_iso_format = $monday->format( 'Y-m-d');

        $json_repas = get_transient( "week_meal_{$monday_iso_format}");

        if( ! $json_repas ){

            $friday = new DateTime( "friday this week {$monday_iso_format}" );
            $friday_iso_format = $friday->format( 'Y-m-d');


            /**
             *
             * Data Nantes pour récupérer les repas de la semaine
             *
             * Pour la semaine :
             * http://data.nantes.fr/donnees/fonctionnement-de-lapi/documentation-de-lapi/
             * http://data.nantes.fr/donnees/detail/menus-des-cantines-scolaires-de-la-ville-de-nantes/
             */

            $json_repas_response = wp_remote_get( 'http://data.nantes.fr/api/publication/24440040400129_VDN_VDN_00171/Menus_cantines_vdn_STBL/content/?format=json&filter={"$and":[{"date":{"$gte":"'.$monday_iso_format.'T00:00:00.000Z"}},{"date":{"$lte":"'. $friday_iso_format .'T00:00:00.000Z"}}]}');

            if( ! is_wp_error( $json_repas_response ) ) {

                $json_repas = json_decode( wp_remote_retrieve_body( $json_repas_response ) );

                if ( $json_repas->nb_results > 0 ){
                    set_transient( "week_meal_{$monday_iso_format}", $json_repas, WEEK_IN_SECONDS );
                }


            } else {

                $json_repas = false;
            }
        }


        if( $json_repas ){


            echo $args['before_widget'];

            $i18n_mondey = mysql2date( 'd F', $monday->format( 'Y-m-d' ) );

            echo "{$args['before_title']}Repas de la semaine du {$i18n_mondey}{$args['after_title']}";

            if( $json_repas->nb_results > 0 ){

                foreach( $json_repas->data as $day ){

                    $meal_date = new DateTime( $day->date->{'$date'} );

                    // On affiche pas les menus "allergique"
                    if( strpos( $day->titre, 'allergique' ) === false && $meal_date->format( 'N' ) != '3' ){

                        $day_of_the_week = ucfirst( mysql2date( 'l', $meal_date->format( 'c')  ) );

                        echo "<div class='nantes-repas-day-menu-wrapper'><h2 class='nantes-repas-day-title'>{$day_of_the_week}</h2><p class='nantes-repas-day-menu'>{$day->repas}</p><p></p></div>";
                    }

                }
            } else {
                echo '<p>';
                _e('Les menus ne sont pas encore disponible pour cette semaine');
                echo '</p>';
            }

            $monday->sub(new DateInterval('P7D'));
            $prev_week = add_query_arg( 'catine-week', $monday->format( 'Y-m-d' ) );
            $monday->add(new DateInterval('P14D'));
            $next_week = add_query_arg( 'catine-week', $monday->format( 'Y-m-d' ) );
            ?>
            <nav class="nantes-repas-week-nav"><a href="<?php echo $prev_week ?>">Précédent</a> <a href="<?php echo $next_week ?>">Suivant</a> </nav>
            <?php


            echo $args['after_widget'];
        }

    }

}

add_action( 'widgets_init', function(){
    register_widget( 'Nantes_Repas_Cantine' );
});