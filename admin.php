<?
class Hacman_Admin_Page extends scbAdminPage {

	

	function setup() {
		$this->args = array(
			'page_title' => 'Hacman',
		);
	}


	function page_content() {
		echo html( 'h2', 'GoCardless Settings' );

		echo $this->form_table(
			array(
				array(
					'title' => 'GoCardless API Key',
					'type' => 'text',
					'name' => 'gocardless_api_key',
					'value' => esc_attr( get_option('gocardless_api_key') )
				),
				array(
					'title' => 'GoCardless Environment',
					'type' => 'text',
					'name' => 'gocardless_env',
					'value' => esc_attr( get_option('gocardless_api_key') ),
					'desc' => '"production" else will default to sandbox'
				),
			)
		);

	}

}



function hacman_additional_fields($user)
{
	print "<h2>Hacman - GoCardless Integration</h2>";
	print "<p>Only Admins can change this - if you need to change it please email board@hacman.org.uk</p>";
	print "<table class='form-table'>";
	print "<tr>";
	print "<th>";
	print "<label for='gocardless_ref'>" . _e('GoCardless Reference', 'hacman_gocardless') . "</label>";
	print "</th>";
	print "<td>";
	print "<input type='text' name='gocardless_ref' id='gocardless_ref' value='" . esc_attr(get_the_author_meta('gocardless_ref', $user->ID)) . "' class='regular-text' />";
	print "<br/><span class='description'>";
	print _e('This is your gocardless reference so we know to update your fob when your payments have gone through.', 'hacman_gocardless');
	print "</span>";
	print "</td>";
	print "</tr>";

	print "<tr>";
	print "<th>";
	print "<label for='redirectflow_ref'>" . _e('Redirect Reference', 'hacman_gocardless') . "</label>";
	print "</th>";
	print "<td>";
	print "<input type='text' name='redirectflow_ref' id='redirectflow_ref' value='" . esc_attr(get_the_author_meta('redirectflow_ref', $user->ID)) . "' class='regular-text' />";
	print "<br/><span class='description'>";
	print _e('Your redirect flow reference, for debug only', 'hacman_gocardless');
	print "</span>";
	print "</td>";
	print "</tr>";

	print "<tr>";
	print "<th>";
	print "<label for='gocardless_sess'>" . _e('Gocardless Session', 'hacman_gocardless') . "</label>";
	print "</th>";
	print "<td>";
	print "<input type='text' name='gocardless_sess' id='gocardless_sess' value='" . esc_attr(get_the_author_meta('gocardless_sess', $user->ID)) . "' class='regular-text' />";
	print "<br/><span class='description'>";
	print _e('Your redirect gocardless session, for debug only', 'hacman_gocardless');
	print "</span>";
	print "</td>";
	print "</tr>";

	print "<tr>";
	print "<th>";
	print "<label for='hacman_keyfob'>" . _e('Keyfob ID', 'hacman_gocardless') . "</label>";
	print "</th>";
	print "<td>";
	print "<input type='text' name='hacman_keyfob' id='hacman_keyfob' value='" . esc_attr(get_the_author_meta('hacman_keyfob', $user->ID)) . "' class='regular-text' />";
	print "<br/><span class='description'>";
	print _e('The ID of your keyfob. This ID is used when you scan in.', 'hacman_gocardless');
	print "</span>";
	print "</td>";
	print "</tr>";

	print "</table>";

}

function hacman_save_additional_fields($user_id)
{
	if (!current_user_can('edit_user', $user_id)) {
		return false;
	}

	if(current_user_can('administrator') ) {
		update_usermeta($user_id, 'gocardless_ref', $_POST['gocardless_ref']);
		update_usermeta($user_id, 'gocardless_sess', $_POST['gocardless_sess']);
		update_usermeta($user_id, 'redirectflow_ref', $_POST['redirectflow_ref']);
		update_usermeta($user_id, 'hacman_keyfob', $_POST['hacman_keyfob']);
	}
}



// Hooks and filters
add_action('show_user_profile', 'hacman_additional_fields');
add_action('edit_user_profile', 'hacman_additional_fields');
add_action('personal_options_update', 'hacman_save_additional_fields');
add_action('edit_user_profile_update', 'hacman_save_additional_fields');

