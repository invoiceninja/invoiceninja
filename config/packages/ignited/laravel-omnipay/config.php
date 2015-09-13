<?php

return array(

	// The default gateway to use
	'default' => 'paypal',

	// Add in each gateway here
	'gateways' => array(
		'paypal' => array(
			'driver' => 'Paypal_Express',
			'options' => array(
				'solutionType' => '',
				'landingPage' => '',
				'headerImageUrl' => ''
			)
		)
	)
);