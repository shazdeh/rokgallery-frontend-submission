<?php
/*
Plugin Name:    RokGallery Frontend Submission
Description:    Enables users to upload images to your RokGallery galleries!
Author:         Hassan Derakhshandeh
Version:        0.1
Author URI:     http://shazdeh.me/

		This program is free software; you can redistribute it and/or modify
		it under the terms of the GNU General Public License as published by
		the Free Software Foundation; either version 2 of the License, or
		(at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

defined( 'ABSPATH' ) or die( '-1' );

class RokGallery_Frontend_Submission extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'classname' => 'widget_rg_frontend_submission', 'description' => __('Display the public image upload form.') );
		parent::__construct( 'rg-frontend-submission', __('RokGallery Frontend Submission'), $widget_ops, null );
	}

	function widget( $args, $instance ) {
		extract( $args );

		$instance = wp_parse_args( $instance, $this->get_defaults() );
		$title = apply_filters( 'widget_title', $instance['title'] );

		if( false !== $this->do_upload() ) {
			$success_message = $instance['success_message'];
		}

		include( $this->get_template_hierarchy( 'widget' ) );
	}

	public function do_upload() {
		if( ! count( $_FILES ) == 0 && isset( $_POST['rg_frontend_submission_nonce_field'] ) && wp_verify_nonce( $_POST['rg_frontend_submission_nonce_field'],'rg_frontend_submission' ) ) {
			$job = RokGallery_Job::create( RokGallery_Job::TYPE_IMPORT );
			$tx = RokGallery_Doctrine::getConnection()->transaction;
			$tx->setIsolation('READ UNCOMMITTED');
			$job_properties = $job->getProperties();

			$basepath = RokGallery_Config::getOption( RokGallery_Config::OPTION_JOB_QUEUE_PATH ) . DS . $job->getId();
			if( ! file_exists( $basepath ) ) {
				@mkdir( $basepath );
				RokGallery_Queue_DirectoryCreate::add( $basepath );
			}

			if( ! ( file_exists( $basepath ) && is_dir( $basepath ) && is_writable( $basepath ) ) ) {
				throw new RokGallery_Job_Exception(rc__('ROKGALLERY_UNABLE_TO_CREATE_OR_WRITE_TO_TEMP_DIR', $basepath));
			}

			$tx->beginTransaction();
			foreach( $_FILES as $uploaded_file ) {
				if( $uploaded_file['error'] == UPLOAD_ERR_OK ) {
					$file = new RokGallery_Job_Property_ImportFile();
					$file->setFilename( $uploaded_file['name'] );
					$file->setPath( $basepath . DS . $file->getId() );
					move_uploaded_file( $uploaded_file['tmp_name'], $file->getPath() );
					$job_properties[] = $file;
				}
			}
			$job->setProperties( $job_properties );
			$job->save();
			$tx->commit();
			/*-- upload done --*/

			$job->Ready("Job is ready to process.");
			$job->Run('Starting Import');
			$job->process();

			// fail. the import job takes time and thus cannot get the proper ID
			// RokGalleryAdminAjaxModelFile::addTags( array( 'id' => {RG IMAGE FILE ID}, 'tags' => array() ) );
			return true;
		}

		return false;
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( $instance, $this->get_defaults() );
		require( $this->get_template_hierarchy( 'form' ) );
	}

	/**
	 * Default widget options
	 *
	 * @return array
	 */
	function get_defaults() {
		return array(
			'success_message' => __( 'Upload was successful. Thank you!' ),
		);
	}

	static function register() {
		register_widget( __CLASS__ );
	}

	function get_template_hierarchy( $template ) {
		// whether or not .php was added
		$template_slug = rtrim( $template, '.php' );
		$template = $template_slug . '.php';

		if ( $theme_file = locate_template( array( 'html/rgfs/' . $template ) ) ) {
			$file = $theme_file;
		} else {
			$file = 'views/' . $template;
		}
		return apply_filters( 'rgfs_' . $template, $file );
	}
}

add_action( 'widgets_init', array( 'RokGallery_Frontend_Submission', 'register' ) );