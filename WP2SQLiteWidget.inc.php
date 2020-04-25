<?php 

class WP2SQLite_Widget extends WP_Widget{

	//contructor de la clase 
	function WP2SQLite_Widget() {	
		//parametros para la llamada a parent	
		$widget_ops = array(
			'classname' => 'widget_wp2sqlite',
			'description' => __( 'Descarga tu blog en una base de datos SQLite.' ),//descripcion del widget
			'customize_selective_refresh' => true,
			);
		//llamada al contructor de la clase padre
		parent::__construct( 'wp2sqlite', _x( 'WP2SQLite', 'WP2SQLite widget' ), $widget_ops );
	}

	//funcion que dibuja el widget 
	function widget( $args, $instance ) {
		

		$title = apply_filters( 
			'widget_title', 
			empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		
		extract( $args );
		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		//contenido del widget
		?>
		<form method="get" action="<?php echo WP2SQLite::get_download_link(); ?>">
		    <input type="checkbox" name="content" id="content" disabled checked/>Contenido</br>
			<input type="checkbox" name="image" id="image"/>Im&aacutegenes</br>
			<input type="checkbox" name="audio" id="audio"/>Audio</br>
			<input type="checkbox" name="video" id="video"/>Videos</br>
			<button type="submit" name="wp2sqlite" value="Descargar">
			<img type="image" name="wp2sqlite" width="16" height="16"
			src="<?php echo file_get_contents(plugins_url().'/WP2SQLite/download.img') ?>"
			alt="Submit"/> Descargar</button>
		</form>
		<?php
		//fin del contenido
		
		echo $after_widget;
	}

	//dibuja la parte de las opciones del widget
	function form($instance){
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = $instance['title'];
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('T&iacutetulo:'); ?> 
		<input 
			class="widefat" 
			id="<?php echo $this->get_field_id('title'); ?>" 
			name="<?php echo $this->get_field_name('title'); ?>" 
			type="text" 
			value="<?php echo esc_attr($title); ?>" 
			onkeypress='return x(event)'
			/>
		</label></p>
		<script type="text/javascript">
			var regex = new RegExp("([0-9A-Za-z ])+");
			function x(event){
				return event.key.match(regex) ? true : false;
			}
		</script>
		<?php
	}

	//funcion para actualizar el titulo
	function update($new_instance,$old_instance){
		$instance = $old_instance;
		$new_instance = wp_parse_args((array) $new_instance, array( 'title' => ''));
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		return $instance;
	}

}