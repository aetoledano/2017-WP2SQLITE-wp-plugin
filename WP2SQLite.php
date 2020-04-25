<?php
/*
Plugin Name: WP2SQLite
Description: Plugin para descargar el blog en una base de datos SQLite.
Version:     20170216
Author:      Rolando Vázquez Conyedo
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

//incluir los archivos del plugin
require_once(plugin_dir_path( __FILE__ ).'WP2SQLiteController.inc.php');
require_once(plugin_dir_path( __FILE__ ).'WP2SQLiteWidget.inc.php');
//listo

//registrar las funciones de activacion del plugin
register_activation_hook( __FILE__, 'on_WP2SQLite_activation' );
register_deactivation_hook(__FILE__, 'on_WP2SQLite_deactivation');
//listo

//action and filters HOOKS

//este hook se utiliza para decirle a wordpress
//que llame la funcion register_WP2SQLite_Widget durante
//la inicializacion de los widgets
add_action('widgets_init','register_WP2SQLite_Widget');
//end

//funcion que se llama durante la activacion del plugin
function on_WP2SQLite_activation(){ 
    WP2SQLite::init_plugin();
}

//funcion que se llama durante la desactivacion del plugin
function on_WP2SQLite_deactivation(){
    WP2SQLite::close_plugin();
}

//funcion para registrar la clase WP2SQLite_Widget(widget)
function register_WP2SQLite_Widget(){
    register_widget('WP2SQLite_Widget');
}


