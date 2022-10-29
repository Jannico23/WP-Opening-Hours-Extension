<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://jnl.local
 * @since      1.0.0
 *
 * @package    Ohx
 * @subpackage Ohx/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Ohx
 * @subpackage Ohx/admin
 * @author     JNL <admin@mcpe.ch>
 */
class Ohx_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Ohx_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Ohx_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/ohx-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Ohx_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Ohx_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/ohx-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Add IC Quick Set sub menu iten, to Opening Hours Settings
	 */
	public function ohx_admin_quickset_menu(){
	
		add_submenu_page("edit.php?post_type=op-set",
						"IC Quick Set", 
						"IC Quick Set", 
						"ohx-quickset", 
						"list-ic", array($this, "list_ic"));
	  
	}


	public function do_output_buffer() {
		ob_start();
	}

	/**
	 * This function loads the schedule (sets) for all facilities (i.e. post of type 'op-set')
	 * and then the irregular closing (ic) records for each facility, which are stored as post-meta data in the 
	 * database.
	 * 
	 * It returns an array:
	 * 	$ic_sets 
	 * 	[ PostID		// id of the post in the database
	 * 	  name			// name of the post (facility)
	 * 	  meta-index	// index of an active ic in the meta array
	 * 	  meta 			// array with post meta records (irregular closing)
	 *    [ name		// Name (reason for irregular closing)
	 *      date		// date of ic
	 *      timeStart	// start time of ic
	 *      timeEnd]]	// end time of ic
	 */
	public function ic_load_sets(){
		
		$ic_sets = array(); // array used to store the
		$now = Dates::getNow();
		
		// prepare the WP_Query
		$args = array(
			'post_type' => 'op-set',
			'post_status' => 'publish',
			'posts_per_page' => 20,
			'orderby' => 'title',
			'order' => 'ASC',
		);
		$related_items = new \WP_Query( $args );

		// retrieve all posts ()
		$ic_set_index = 0;
		while ( $related_items->have_posts() ) : $related_items->the_post();
			$ic_post_id = get_the_ID();
			$ic_sets[$ic_set_index]['PostID'] = $ic_post_id;
			$ic_sets[$ic_set_index]['name'] = get_the_title();
			$ic_sets[$ic_set_index]['meta-index'] = -1; // by default no ic active

			// get irregular closing entries from the post
			// meta data
			$ic_meta = get_post_meta($ic_post_id, '_op_set_irregular_closings', true);

			// check if there is already an irregular closing active
			// i.e. $now matches $date and is between $timeStart and $timeEnd
			// If so, we remember the index (meta-index) of the matching ic record
			// otherwise leave the meta-index to -1 
			if (count($ic_meta) > 0) {
				for ($i = 0; $i < count($ic_meta); $i++) {
				  
				  	$date = $ic_meta[$i]['date'];
				  	$timeStart = $ic_meta[$i]['timeStart'];
				  	$timeEnd = $ic_meta[$i]['timeEnd'];
				  	$ic_start = Dates::mergeDateIntoTime(new DateTime($date), new DateTime($timeStart));
				  	$ic_end = Dates::mergeDateIntoTime(new DateTime($date), new DateTime($timeEnd));
		
				  	// check if $now is between start and end -> that means, ic is already active
				  	$diff1 = Dates::compareDateTime($now,$ic_start);
				  	$diff2 = Dates::compareDateTime($now,$ic_end);
					if(Dates::compareDateTime($now,$ic_start) >= 0 && Dates::compareDateTime($now,$ic_end) <= 0) {
						$ic_sets[$ic_set_index]['meta-index'] = $i; // remember index of active ic record
					}
				}
			}
			// add the aray of irregular closings to the $ic_sets
			$ic_sets[$ic_set_index]['meta'] = $ic_meta;
			$ic_set_index++;
		endwhile;

		return $ic_sets;
	}


	/** 
	 * This function builds the admin page itselfallowing to change the 
	 * opening status for each facility List Irregular Closings sub-page
	 * 
	 * We use a form to collect the user input and the POST method to
	 * pass the data to the registered call callback function for processing
	 *  
	 */
	public function list_ic(){
	
		// check if the current user is allowed to call this function
		if ( !current_user_can( 'ohx-quickset' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		  }

		// dummy record used to populate the $ic_tmp_data, if there is no
		// irregular closing active for the current date/time 
		$ic_dummy = array(
			'name' => '',
			'date' => date('Y-m-d'),
			'timeStart' => '00:00',
			'timeEnd' => '23:55',
		);

		// get all schedules including all meta records (irregular closings)
		$ics = $this->ic_load_sets();
		/**
		 * build the temporary list for the page
		 * $ics_page
		 * 	[ PostID		// id of the post in the database
		 * 	  name			// name of the post (facility)
		 * 	  meta-index	// index of an active ic in the meta array
		 * 	  meta 			// array with post meta records (irregular closing)
		 *    [ name		// Name (reason for irregular closing)
		 *      date		// date of ic
		 *      timeStart	// start time of ic
		 *      timeEnd		// end time of ic
		 *    ]
		 *    status		// opening status of facility
		 *  ]	
		 */

		$ics_page = array();
		for ($i = 0; $i < count($ics); $i++) {
			$ics_page[$i]['PostID'] = $ics[$i]['PostID']; // just copy the PostID
			$ics_page[$i]['name'] = $ics[$i]['name']; // just copy the name
			$ics_page[$i]['meta-index'] = $ics[$i]['meta-index']; // just copy the index
			
			/** if an irregular closing is active (meta-index >= 0), 
			 * we copy the corresponding meta data into our $ics_page record  and set the status to closed (1)
			 * otherwise copy the dummy record into the $ics_page record  
			 */
			if ($ics[$i]['meta-index'] >= 0 ) {
				$ics_page[$i]['meta'] = $ics[$i]['meta'][$ics[$i]['meta-index']]; // just copy the PostID
				$ics_page[$i]['status'] = 1; // there is an active ic setting
			}
			else {
				$ics_page[$i]['meta'] = $ic_dummy; // add dummy
				$ics_page[$i]['status'] = 0; // there is NO active ic setting
			}
		}

		//=========== build the page ====================
		// print heading, if we are on the correct page
		if (isset($_GET['page']) == 'list-ic') {
 				 echo "<h1>";
				 _e("QuickSet",'ohx');
				 echo "</h1>";
		}
		
		?>
		<div class="wrap">
			<h1><?php _e("List of Irregular Closings",'ohx');?></h1>
			<form action="<?php echo esc_attr( admin_url('admin-post.php')); ?>" method="post">
			<input type="hidden" name="action" value="quickset-form" />
			<table class="wp-lit-table widefat striped table-ic" id="table_ic"> 
				<thead>
					<tr>
						<th><?php _e("Facility",'ohx');?></th>
						<th><?php _e("Status",'ohx');?></th>
						<th><?php _e("Reason",'ohx');?></th>
					</tr>	
				</thead>
				<tbody></tbody>
				<?php 
				foreach($ics_page as $ic) { ?>
					<tr>
						<td><?php echo $ic['name'] ?></td>
						<td><input type="checkbox" class="wppd-ui-toggle" id="ic-status" name="ic-status[]" value="<?php echo $ic['PostID'] ?>" <?php echo ($ic['status'] === 1) ? 'checked' : ''; ?>></td>
						<td><input type="text" id="ic-reason" name="ic-reason[]" value="<?php echo $ic['meta']['name']?>"</td>
						<input type="hidden" id="ic-post-id"    name="ic-post-id[]"    value="<?php echo $ic['PostID']?>">
						<input type="hidden" id="ic-meta-index" name="ic-meta-index[]" value="<?php echo $ic['meta-index']?>">
					</tr>
					<?php
				}
				?>
			</table>
			<!-- 
			 ! prepare $ics_page and $ics structures to tranfer them via the post method
			 ! to the callbackfunction ohx_admin_quickset_callback() for further processing
			-->
			<input type="hidden" id="ics-tmp" name="ics-tmp" value="<?php echo htmlspecialchars(json_encode($ics_page))?>">
			<input type="hidden" id="ics" name="ics" value="<?php echo htmlspecialchars(json_encode($ics))?>">
			<?php
				submit_button();
			?>
			</form>
		</div>
	<?php
	}

	/**
	 * This function evaluates, if a a checkbox for a particular
	 * facility was set to closed.
	 * 
	 * The POST method of the form does only submit values for enabled checkboxes! Therefore
	 * we need to check, if the current postID ($id) is listed in the array 
	 * of $closed_posts (i.e. id of posts, where the checkbox was set to closed)
	 *
	 * 	It returns:
	 *  1 if the checkbox is set to closed
	 *  0 if the checkbox is set to open
	 */
	public function set_to_closed($closed_posts, $id) {

		if(!empty($closed_posts)) {
			$search_results = array();
			foreach($closed_posts as $post_id) {
				if($id == $post_id) {
					return 1; // this facility is set to closed
				}   
			} 
		}   
		return 0;
	}


	/**
	 * This function evaluates, which transition was med on the input form for
	 * a particular facility.
	 * 
	 * 	It returns:
	 *  -1 if open->closed
	 *   0 if no transition
	 *   1 if closed->open
	 */
	public function get_transition($id, $index, $status){

		// open->closed
		if($index < 0 && $this->set_to_closed($status, $id)) {
			return -1;
		}

		// closed->open
		if($index >= 0 && !$this->set_to_closed($status, $id)) {
			return 1;
		}

		// not transition
		return 0;
	}
	
	
	
	
	
	/**
	 * This function is updating the database according to the settings on the admin form.
	 * 
	 * Rules:
	 * - add post_meta dummy if the status transition was open->closed
	 * - update post_meta if there was no status transition but ic_reason was changed 
	 * - delete post_meta if the status transition was closed->open
	 */

	public function ohx_admin_quickset_callback() {
		// retrieve the data tranferred through the POST method
		// ic_posts:        array with the post_IDs (used to update a post if required)
		// ic_meta_index:   array referring to the post_meta entry, if an ic was alread active for today
		// ic_reasons:      array providing the reason for the closing
		// ic_status:       array listing the post_IDs, where the checkbox (slider) was set to closed
		// ics_page:        json with all Posts of the type 'op-set' used by the admin page 
		// ics:             json with all Posts of the type 'op-set' including all post_meta entries


		$ic_posts = $_POST['ic-post-id'];
		$ic_meta_index = $_POST['ic-meta-index'];
		$ic_reasons = $_POST['ic-reason'];
		$ic_status = $_POST['ic-status'];
		$ics_page = stripslashes($_POST['ics-tmp']);
		$ics = stripslashes($_POST['ics']);

		$ics_page_a = json_decode(htmlspecialchars_decode($ics_page),true);
		$ics_a = json_decode(htmlspecialchars_decode($ics),true);

		for ($i = 0; $i < count($ic_posts); $i++) {

			$post_id = $ic_posts[$i];
			$transition = $this->get_transition($post_id, $ic_meta_index[$i], $ic_status);

			/**
			 * If the checkbox was set from open to closed the we add
			 * a new post_meta etry using the dummy record with today's date 
			 * starting at 00:00, ending at 23:55 and with the the reason entered in the form
			 */
			if($transition == -1 ) {
				// prepare post_meta entry
				$ic_meta_entry = array(
					'name' => $ic_reasons[$i],
					'date' => date('Y-m-d'),
					'timeStart' => '00:00',
					'timeEnd' => '23:55',
				);

				// append dummy to post_meta array
				$new_post_meta = array();
				for ($m = 0; $m < count($ics_a[$i]['meta']); $m++){
					array_push($new_post_meta, $ics_a[$i]['meta'][$m]);
				}
				array_push($new_post_meta, $ic_meta_entry);
				// add post_meta to DB
				update_post_meta($post_id,'_op_set_irregular_closings',$new_post_meta);

			}

			/**
			 * If there was no transition, but the reason was changed, we update the existing 
			 * post_meta record with the new reason
			 */
			if ($transition == 0 && $ic_reasons[$i] != $ics_page_a[$i]['meta']['name']){
				// copy old post_meta
				$new_post_meta = $ics_a[$i]['meta']; // copy old post_meta
				// update post_meta arry with new reason
				$new_post_meta[$ic_meta_index[$i]]['name'] = $ic_reasons[$i]; //replace name with new reason
				//update post_meta in DB
				update_post_meta($post_id,'_op_set_irregular_closings',$new_post_meta);


			}

			/**
			 * If there was a transition from closed to open, we have to 
			 * remove the post_meta entry for today's date. 
			 * 
			 */
			if ($transition == 1) {
				// copy old post_meta
				$new_post_meta = $ics_a[$i]['meta'];
				
				// delete entry from post_meta array
				unset($new_post_meta[$ic_meta_index[$i]]);
				
				//re-index array
				$new_post_meta_r = array_values($new_post_meta);
				//update post_meta
				update_post_meta($post_id,'_op_set_irregular_closings',$new_post_meta_r);
			}

	
		}
		/** refresh the page after updating the database */
		wp_redirect($_SERVER['HTTP_REFERER']);
	}

}