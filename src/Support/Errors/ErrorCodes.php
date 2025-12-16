<?php
/**
 * Centralized WP_Error code constants.
 *
 * @package Airygen\Support\Errors
 */

declare(strict_types=1);

namespace Airygen\Support\Errors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical error codes used across REST/controllers.
 */
final class ErrorCodes {

	public const AIRYGEN_FORBIDDEN                               = 'airygen_forbidden';
	public const AIRYGEN_INDEXNOW_INVALID_ACTION                 = 'airygen_indexnow_invalid_action';
	public const AIRYGEN_INDEXNOW_MISSING_HOST                   = 'airygen_indexnow_missing_host';
	public const AIRYGEN_INDEXNOW_MISSING_POST_TYPES             = 'airygen_indexnow_missing_post_types';
	public const AIRYGEN_INDEXNOW_MISSING_URLS                   = 'airygen_indexnow_missing_urls';
	public const AIRYGEN_INVALID_POST                            = 'airygen_invalid_post';
	public const AIRYGEN_POST_NOT_FOUND                          = 'airygen_post_not_found';
	public const BAD_REQUEST                                     = 'bad_request';
	public const INVALID_CHANNEL                                 = 'invalid_channel';
	public const INVALID_ID                                      = 'invalid_id';
	public const NOT_FOUND                                       = 'not_found';
	public const REST_FORBIDDEN                                  = 'rest_forbidden';
	public const AIRYGEN_DEBUG_DIR                               = 'airygen_debug_dir';
	public const AIRYGEN_DEBUG_INVALID_DATE                      = 'airygen_debug_invalid_date';
	public const AIRYGEN_SETTINGS_INVALID_PAYLOAD                = 'airygen_settings_invalid_payload';
	public const AIRYGEN_TOPIC_CLUSTER_CANDIDATE_ALREADY_RELATED = 'airygen_topic_cluster_candidate_already_related';
	public const AIRYGEN_TOPIC_CLUSTER_CANDIDATE_CREATE_FAILED   = 'airygen_topic_cluster_candidate_create_failed';
	public const AIRYGEN_TOPIC_CLUSTER_CANDIDATE_DELETE_FAILED   = 'airygen_topic_cluster_candidate_delete_failed';
	public const AIRYGEN_TOPIC_CLUSTER_CANDIDATE_EXISTS          = 'airygen_topic_cluster_candidate_exists';
	public const AIRYGEN_TOPIC_CLUSTER_CANDIDATE_INVALID         = 'airygen_topic_cluster_candidate_invalid';
	public const AIRYGEN_TOPIC_CLUSTER_CANDIDATE_POST_NOT_FOUND  = 'airygen_topic_cluster_candidate_post_not_found';
	public const AIRYGEN_TOPIC_CLUSTER_CHILDREN_LOCKED           = 'airygen_topic_cluster_children_locked';
	public const AIRYGEN_TOPIC_CLUSTER_FORBIDDEN                 = 'airygen_topic_cluster_forbidden';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_CREATE_FAILED       = 'airygen_topic_cluster_group_create_failed';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_DELETE_FAILED       = 'airygen_topic_cluster_group_delete_failed';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_INVALID_ID          = 'airygen_topic_cluster_group_invalid_id';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_INVALID         = 'airygen_topic_cluster_group_map_invalid';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_MAP_UPDATE_FAILED   = 'airygen_topic_cluster_group_map_update_failed';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_NAME_REQUIRED       = 'airygen_topic_cluster_group_name_required';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_EMPTY           = 'airygen_topic_cluster_group_not_empty';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_NOT_FOUND           = 'airygen_topic_cluster_group_not_found';
	public const AIRYGEN_TOPIC_CLUSTER_GROUP_UPDATE_FAILED       = 'airygen_topic_cluster_group_update_failed';
	public const AIRYGEN_TOPIC_CLUSTER_INVALID_LEVEL             = 'airygen_topic_cluster_invalid_level';
	public const AIRYGEN_TOPIC_CLUSTER_INVALID_ORDER_HANDLES     = 'airygen_topic_cluster_invalid_order_handles';
	public const AIRYGEN_TOPIC_CLUSTER_INVALID_ORDER_NODES       = 'airygen_topic_cluster_invalid_order_nodes';
	public const AIRYGEN_TOPIC_CLUSTER_INVALID_ORDER_SCOPE       = 'airygen_topic_cluster_invalid_order_scope';
	public const AIRYGEN_TOPIC_CLUSTER_INVALID_PARENT            = 'airygen_topic_cluster_invalid_parent';
	public const AIRYGEN_TOPIC_CLUSTER_INVALID_POST              = 'airygen_topic_cluster_invalid_post';
	public const AIRYGEN_TOPIC_CLUSTER_INVALID_RELATION          = 'airygen_topic_cluster_invalid_relation';
	public const AIRYGEN_TOPIC_CLUSTER_L1_EXISTS                 = 'airygen_topic_cluster_l1_exists';
	public const AIRYGEN_TOPIC_CLUSTER_MISSING_ENTRY             = 'airygen_topic_cluster_missing_entry';
	public const AIRYGEN_TOPIC_CLUSTER_MISSING_PARENT            = 'airygen_topic_cluster_missing_parent';
	public const AIRYGEN_TOPIC_CLUSTER_MISSING_TABLE             = 'airygen_topic_cluster_missing_table';
	public const AIRYGEN_TOPIC_CLUSTER_PARENT_NOT_ALLOWED        = 'airygen_topic_cluster_parent_not_allowed';
	public const AIRYGEN_TOPIC_CLUSTER_SAVE_FAILED               = 'airygen_topic_cluster_save_failed';
	public const AIRYGEN_WOOCOMMERCE_UNAVAILABLE                 = 'airygen_woocommerce_unavailable';
}
