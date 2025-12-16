<?php
// Stubs for PHPStan so WordPress/Action Scheduler symbols resolve during analysis.

// phpcs:ignoreFile
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	function as_next_scheduled_action( $hook, $args = array(), $group = '' ) {
		return false;
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '' ) {
		return 0;
	}
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	function as_enqueue_async_action( $hook, $args = array(), $group = '' ) {
		return 0;
	}
}

if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	function as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
		return 0;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) {
		return 0;
	}
}

if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
	function as_get_scheduled_actions( $args = array(), $return_format = 'OBJECT' ) {
		return array();
	}
}

if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	function as_has_scheduled_action( $hook, $args = array(), $group = '' ) {
		return false;
	}
}

if ( ! class_exists( 'ActionScheduler_Store' ) ) {
	class ActionScheduler_Store {
		public static function instance() {
			return new self();
		}
	}
}
