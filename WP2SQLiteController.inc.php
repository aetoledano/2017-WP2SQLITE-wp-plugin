<?php

class WP2SQLite{

  //tipos de contenido que se van a descargar
  static $media_type;

  //direccion fisica de los archivos(adjuntos) en el servidor
  static $files;

  //stream de la base de datos
  static $db_file;

  //nombre identificador del atachment
  static $guids;

  //ancho de las etiquetas imagenes, audio o video
  static $size_in_pixels = 300;

  //genera la url del controlador de la descarga
  static function get_download_link(){
    return plugins_url().'/WP2SQLite/download.php';
  }

  //devuelve un stream al archivo .zip que contiene la base de datos y el contenido multimedia
  static function get_download_stream($media_type_params){
    //inicializacion
    WP2SQLite::$media_type = $media_type_params;
    WP2SQLite::$files = array();
    WP2SQLite::$guids = array();
    WP2SQLite::$db_file = tmpfile();//tmpfile() genera un archivo temporal y devuelve un stream del archivo que creo

    $meta = stream_get_meta_data(WP2SQLite::$db_file);
    
    //crear la base de datos
    R::setup( 'sqlite:'.$meta['uri'] );
    //poner los datos dentro de la base de datos a traves de una transaccion
    R::transaction(function(){ WP2SQLite::putData(); });
    //cerrar la conexion con la base de datos
    R::freeze(true);
    R::close();

    //reiniciar el apuntador del stream
    rewind(WP2SQLite::$db_file);

    //meter el contenido multimedia(en caso de que s deba) y la base dedatos en el zip
    $zipstream = WP2SQLite::zip_download();
    
    //cierra el archivo de la base de datos
    fclose(WP2SQLite::$db_file);

    //devolver el stream
    return $zipstream;
  }

  //genera el .zip que se va a descargar
  private static function zip_download(){
    //crear el archivo temporal donde se va generar el zip
    $tmpfile = sys_get_temp_dir().'/'.time();
    touch($tmpfile);

    //comprueba que el archivo se haya creado
    if (!is_writable($tmpfile)){
      die("Error: Unable to write to tmp directory. Check Permissions!");
    }

    //crear objeto de la clase ZipArchive de PHP
    $zip = new ZipArchive();

    //abrir el archivo temporal y añadir los adjuntos
    if($zip->open($tmpfile,ZIPARCHIVE::CREATE) === true) {
      foreach(WP2SQLite::$files as $file) {
        $zip->addFile($file,'media/'.basename($file));
        //echo (is_readable($file)?"ok->":"wrong->").basename($file)."->".$file."\n</br>";
      }
    }else{
      die("Error: Cannot open <".$tmpfile."> for writing!\n");
    }

    //añadir la base de datos al archivo
    $dbmeta = stream_get_meta_data(WP2SQLite::$db_file);
    if (!is_readable($dbmeta['uri'])){
      die("Error: Unable to read to generated database file. Check Permissions!");
    }
    $zip->addFile($dbmeta['uri'],'WP2SQLITE.DB');

    //añadir el banner
    $banner_url = get_header_image();
    $zip->addFromString('assets/_banner_header',file_get_contents($banner_url));

    //añadir el titulo del blog
    $bloginfo = get_bloginfo('name');
    $zip->addFromString('assets/title',$bloginfo);

    //añadir el subtitulo 
    $bloginfo = get_bloginfo('description');
    $zip->addFromString('assets/tagline',$bloginfo);

    //añadir la url donde esta publicado el blog
    $bloginfo = get_bloginfo('url');
    $zip->addFromString('assets/url',$bloginfo);

    //mandar a comprimirlo todo
    $zip->close();

    //retornar stream del zip
    return fopen($tmpfile,'rb');
  }

  //inserta el contenido de las paginas y entradas en la base de datos sqlite
  //a traves del ORM RedBeanPHP 
  private static function putData(){
    //obtener los tipos de adjuntos
    $media_type = WP2SQLite::$media_type;

    //insertar los nombres de los archivos multimedia
    //WP2SQLite::putAttachments();
    //listo

    //crear e insertar contenido en la tabla authors 
    $args = array(
      'blog_id' => $GLOBALS['blog_id'],
      'orderby' => 'login',
      'order'   => 'ASC',
      'fields'  => array( 'ID','display_name' )
      );
    $users = get_users( $args );
    unset($args);
    foreach ($users as $user){
      $user_row = R::dispense('authors');
      $user_row->author_id = $user->ID;
      $user_row->display_name = $user->display_name;
      R::store($user_row);
    }
    //listo

    //crear e insertar contenido en la tabla categories
    $cats = get_categories();
    foreach ($cats as $cat){
      $cat_row = R::dispense('categories');
      $cat_row->cat_id = $cat->cat_ID;
      $cat_row->cat_name = $cat->name;
      R::store($cat_row);
    }
    //listo

    //crear e insertar contenido en la tabla entries (post y pages)
    $args = array('numberposts'=>-1,'post_type'=>'any');
    $posts = get_posts($args);
    unset($args);
    foreach ($posts as $p){
      $entry = R::dispense('entries');
      //wordpress convierte el contenido del post a html
      $unfiltered_content = str_replace( '<!--more-->', '', $p->post_content );
      $content = apply_filters( 'the_content', $unfiltered_content );
      $content = str_replace( ']]>', ']]&gt;', $content );
      //arreglar el contenido

      $entry->content = WP2SQLite::parse_content($content);

      $entry->entry_id = $p->ID;
      $entry->title = $p->post_title;
      $entry->post_date = strtotime($p->post_date);
      $entry->post_modified_date=strtotime($p->post_modified);
      $entry->type=$p->post_type;
      $entry->author_id = $p->post_author;
      R::store($entry);

      //obtener las categorias del post y crear la relacion
      $args = array('fields' => 'all_with_object_id');
      $terms = wp_get_post_categories($p->ID,$args);
      unset($args);
      if ($terms){
        foreach ($terms as $term){
          if ( strcmp($term->taxonomy,'category') == 0 ){
            $entry_category = R::dispense('entrycategory');
            $entry_category->cat_id = $term->term_id;
            $entry_category->entry_id = $term->object_id;
            R::store($entry_category);
          }
        }
      }
      //listo
    }
    //die();
    //reiniciar el loop de wordpress
    wp_reset_postdata();
    //listo
  }

  //modifica las etiquetas de los archivos multimedia
  //para su correcta visualizacion en el dispositivo movil
  //a traves de la libreria simple_html_dom.php
  private static function parse_content($content){
    $dom = str_get_html($content);

    //poniendo atributo preload="auto" en los videos
    //y tamaño nulo y añadiendo un tag class para modificar el estilo 
    //en la aplicacion android
    foreach ($dom->find('video') as $vid){
      $vid->preload="auto";
      $vid->width = null;
      $vid->height = null;
      $vid->class="wpr-style";
    }

    //poniendo tamaño nulo y añadiendo un tag class para modificar el estilo 
    //en la aplicacion android
    foreach ($dom->find('img') as $pic){
      $pic->width = null;
      $pic->height = null;
      $pic->class="wpr-style";
      if ($pic->sizes){
        $pic->removeAttribute("sizes");
      }
    }

    //eliminar las etiquetas <a>
    foreach ($dom->find('a') as $a){
      $a->outertext = '';
    }

    //eliminar el width del div con class wp-video
    foreach ($dom->find('div[class=wp-video]') as $a){
      $a->style=null;
    }

    //remplazar el contenido de los atributos src por los nombres de los adjuntos
    $upload = wp_upload_dir();
    foreach ($dom->find('*[src]') as $element){
      $src = preg_replace("/\?\_=[0-9]+$/", "", $element->src);
      $element->src = basename($src);
      WP2SQLite::$files[] = $upload['path'] ."/". $element->src;
      if ($element->srcset){
        $element->removeAttribute("srcset");
      }
    }
    return $dom->save();
  }

  //activacion del plugin
  static function init_plugin(){
    //no usada
  }

  //desactivacion del plugin
  static function close_plugin(){
    //no usada
  }

}
