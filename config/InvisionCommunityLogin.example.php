<?php
// Log in at the community and retrieve the headers from a page load request. optional: Strip away any unneccessary cookies
// I guess a more friendly way to do this would be simply have username / password, but I prefer this approach atm. :P 
$invisionLogins = [
	'community' => [
        'Cookie' => 'ips4_ipsTimezone=Europe/Oslo; ips4_device_key=def456; ips4_IPSSessionFront=123abc; ips4_member_id=999999; ips4_login_key=456def; ips4_loggedIn=1;',
        'User-Agent' => 'Mozilla/5.0 ...'
	]
];
