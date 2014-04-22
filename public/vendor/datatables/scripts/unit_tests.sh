#!/bin/sh

ENABLE=$1

echo ""
echo "  DataTables unit tests"
echo ""

if [ ! "$ENABLE" = "Enable" -a ! "$ENABLE" = "Disable" -o "ENABLE" = "-h" ]; then
	echo "  Enable or Disable must be given as the first argument."
	echo "  Optionally the second argument can be given as an integer to enable/disable a certain "
	echo "  set of tests or the string 'sanity' to run the sanity check for all data types."
	echo ""
	exit 1
fi

cd ../media/unit_testing

if [ "$ENABLE" = "Enable" ]; then
	if [ ! -d tests ]; then
		echo "  Building test directory"
		mkdir tests
		mkdir tests/1_dom
		mkdir tests/2_js
		mkdir tests/3_ajax
		mkdir tests/4_server-side
		mkdir tests/5_ajax_objects
		mkdir tests/6_delayed_rendering
	fi

	echo "  Enabling:"
	if [ ! -z $2 ]; then
		if [ "$2" = "sanity" ]; then
			echo "    Sanity checks"
			mv tests_onhold/1_dom/_zero_config.js tests/1_dom/
			mv tests_onhold/2_js/_zero_config.js tests/2_js/
			mv tests_onhold/3_ajax/_zero_config.js tests/3_ajax/
			mv tests_onhold/4_server-side/_zero_config.js tests/4_server-side/
			mv tests_onhold/5_ajax_objects/_zero_config.js tests/5_ajax_objects/
			mv tests_onhold/6_delayed_rendering/_zero_config.js tests/6_delayed_rendering/
		elif [ $2 -eq 1 ]; then
			echo "    DOM"
			mv tests_onhold/1_dom/* tests/1_dom/
		elif [ $2 -eq 2 ]; then
			echo "    JS"
			mv tests_onhold/2_js/* tests/2_js/
		elif [ $2 -eq 3 ]; then
			echo "    Ajax"
			mv tests_onhold/3_ajax/* tests/3_ajax/
		elif [ $2 -eq 4 ]; then
			echo "    SErver-side"
			mv tests_onhold/4_server-side/* tests/4_server-side/
		elif [ $2 -eq 5 ]; then
			echo "    Ajax objects"
			mv tests_onhold/5_ajax_objects/* tests/5_ajax_objects/
		elif [ $2 -eq 6 ]; then
			echo "    Delayed rendering"
			mv tests_onhold/6_delayed_rendering/* tests/6_delayed_rendering/
		fi
	else
		echo "    All tests"
		mv tests_onhold/1_dom/*               tests/1_dom/
		mv tests_onhold/2_js/*                tests/2_js/
		mv tests_onhold/3_ajax/*              tests/3_ajax/
		mv tests_onhold/4_server-side/*       tests/4_server-side/
		mv tests_onhold/5_ajax_objects/*      tests/5_ajax_objects/
		mv tests_onhold/6_delayed_rendering/* tests/6_delayed_rendering/
	fi

else
	echo "  Disabling:"
	if [ ! -z $2 ]; then
		if [ "$2" = "sanity" ]; then
			echo "    Sanity checks"
			mv tests/1_dom/* tests_onhold/1_dom/
			mv tests/2_js/* tests_onhold/2_js/
			mv tests/3_ajax/* tests_onhold/3_ajax/
			mv tests/4_server-side/* tests_onhold/4_server-side/
			mv tests/5_ajax_objects/* tests_onhold/5_ajax_objects/
			mv tests/6_delayed_rendering/* tests_onhold/6_delayed_rendering/
		elif [ $2 -eq 1 ]; then
			echo "    DOM"
			mv tests/1_dom/* tests_onhold/1_dom/
		elif [ $2 -eq 2 ]; then
			echo "    JS"
			mv tests/2_js/* tests_onhold/2_js/
		elif [ $2 -eq 3 ]; then
			echo "    Ajax"
			mv tests/3_ajax/* tests_onhold/3_ajax/
		elif [ $2 -eq 4 ]; then
			echo "    Server-side"
			mv tests/4_server-side/* tests_onhold/4_server-side/
		elif [ $2 -eq 5 ]; then
			echo "    Ajax objects"
			mv tests/5_ajax_objects/* tests_onhold/5_ajax_objects/
		elif [ $2 -eq 6 ]; then
			echo "    Delayed rendering"
			mv tests/6_delayed_rendering/* tests_onhold/6_delayed_rendering/
		fi
	else
		echo "    All tests"
		mv tests/1_dom/*               tests_onhold/1_dom/
		mv tests/2_js/*                tests_onhold/2_js/
		mv tests/3_ajax/*              tests_onhold/3_ajax/
		mv tests/4_server-side/*       tests_onhold/4_server-side/
		mv tests/5_ajax_objects/*      tests_onhold/5_ajax_objects/
		mv tests/6_delayed_rendering/* tests_onhold/6_delayed_rendering/
	fi
fi
