<?php
//controlador independiente

//comprobar que la peticion se hizo desde el boton
//de la descarga
if ( empty($_GET['wp2sqlite']) ){
    exit;
}
//incluir el framework wordpress
if (!defined('WP_PLUGIN_URL')) {
     require_once( realpath('../../../').'/wp-config.php' );
}
//incluye el orm para manipular la base de datos sqlite
require_once(plugin_dir_path( __FILE__ ).'libs/rb.php');
//incluye la clase simple_html_dom parser para manipular el contenido de 
//las entradas y las paginas 
require_once(plugin_dir_path( __FILE__ ).'libs/simple_html_dom.php');
//incluye el controlador del plugin
require_once(plugin_dir_path( __FILE__ ).'WP2SQLiteController.inc.php');

//comprobar que se va a descargar el contenido multimedia
$media_type = array();
//comprobar imagenes
if ( isset($_GET['image']) && strcmp($_GET['image'],'on') == 0 ){
    $media_type['image'] = true;
}
//comprobar audio
if ( isset($_GET['audio']) && strcmp($_GET['audio'],'on') == 0 ){
    $media_type['audio'] = true;
}
//comprobar video
if ( isset($_GET['video']) && strcmp($_GET['video'],'on') == 0 ){
    $media_type['video'] = true;
}

//obtener el stream de la descaarga
$stream = WP2SQLite::get_download_stream($media_type);

//indicarle al navegador que se va a descargar un archivo
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=WPEverywhere.zip");
header("Cache-Control: no-cache");
set_time_limit(0);

//leer el stream en partes y enviarlas para el navegador
while(!feof($stream)){
    print(@fread($stream, 1024*8));
    ob_flush();
    flush();
}

//obtener los metadatos del stream
$meta = stream_get_meta_data($stream);

//cierra el stream
fclose($stream);

//elimina el zip despues de descargado
unlink($meta['uri']);

//indicar al navegador que la peticion ha terminado
session_write_close();
