<?php return array(

    // Markup
    ////////////////////////////////////////////////////////////////////

    // Whether labels should be automatically computed from name
    'automatic_label'         => true,

    // The default form type
    'default_form_type'       => 'horizontal',

    // Validation
    ////////////////////////////////////////////////////////////////////

    // Whether Former should fetch errors from Session
    'fetch_errors'            => true,

    // Whether Former should try to apply Validator rules as attributes
    'live_validation'         => true,

    // Whether Former should automatically fetch error messages and
    // display them next to the matching fields
    'error_messages'          => true,

    // Checkables
    ////////////////////////////////////////////////////////////////////

    // Whether checkboxes should always be present in the POST data,
    // no matter if you checked them or not
    'push_checkboxes'         => true,

    // The value a checkbox will have in the POST array if unchecked
    'unchecked_value'         => "0",

    // Required fields
    ////////////////////////////////////////////////////////////////////

    // The class to be added to required fields
    'required_class'          => 'required',

    // A facultative text to append to the labels of required fields
    'required_text'           => '',

    // Translations
    ////////////////////////////////////////////////////////////////////

    // Where Former should look for translations
    'translate_from'          => 'texts',

    // Whether text that comes out of the translated
    // should be capitalized (ex: email => Email) automatically
    'capitalize_translations' => false,

    // An array of attributes to automatically translate
    'translatable'            => array(
        'help',
        'inlineHelp',
        'blockHelp',
        'placeholder',
        'data_placeholder',
        'label',
    ),

    // Framework
    ////////////////////////////////////////////////////////////////////

    // The framework to be used by Former
    'framework'               => 'TwitterBootstrap3',

    'TwitterBootstrap3'       => array(

        // Map Former-supported viewports to Bootstrap 3 equivalents
        'viewports'   => array(
            'large'  => 'lg',
            'medium' => 'md',
            'small'  => 'sm',
            'mini'   => 'xs',
        ),
        // Width of labels for horizontal forms expressed as viewport => grid columns
        'labelWidths' => array(
            'large' => 4,
            'small' => 4,
        ),
        // HTML markup and classes used by Bootstrap 3 for icons
        'icon'        => array(
            'tag'    => 'span',
            'set'    => 'glyphicon',
            'prefix' => 'glyphicon',
        ),

    ),

    'Nude'                    => array(  // No-framework markup
        'icon' => array(
            'tag'    => 'i',
            'set'    => null,
            'prefix' => 'icon',
        ),
    ),

    'TwitterBootstrap'        => array( // Twitter Bootstrap version 2
        'icon' => array(
            'tag'    => 'i',
            'set'    => null,
            'prefix' => 'icon',
        ),
    ),

    'ZurbFoundation5'         => array(
        // Map Former-supported viewports to Foundation 5 equivalents
        'viewports'           => array(
            'large'  => 'large',
            'medium' => null,
            'small'  => 'small',
            'mini'   => null,
        ),
        // Width of labels for horizontal forms expressed as viewport => grid columns
        'labelWidths'         => array(
            'small' => 3,
        ),
        // Classes to be applied to wrapped labels in horizontal forms
        'wrappedLabelClasses' => array('right', 'inline'),
        // HTML markup and classes used by Foundation 5 for icons
        'icon'                => array(
            'tag'    => 'i',
            'set'    => null,
            'prefix' => 'fi',
        ),
        // CSS for inline validation errors
        'error_classes'       => array('class' => 'error'),
    ),

    'ZurbFoundation4'         => array(
        // Foundation 4 also has an experimental "medium" breakpoint
        // explained at http://foundation.zurb.com/docs/components/grid.html
        'viewports'           => array(
            'large'  => 'large',
            'medium' => null,
            'small'  => 'small',
            'mini'   => null,
        ),
        // Width of labels for horizontal forms expressed as viewport => grid columns
        'labelWidths'         => array(
            'small' => 3,
        ),
        // Classes to be applied to wrapped labels in horizontal forms
        'wrappedLabelClasses' => array('right', 'inline'),
        // HTML markup and classes used by Foundation 4 for icons
        'icon'                => array(
            'tag'    => 'i',
            'set'    => 'general',
            'prefix' => 'foundicon',
        ),
        // CSS for inline validation errors
        'error_classes'       => array('class' => 'alert-box radius warning'),
    ),

    'ZurbFoundation'          => array( // Foundation 3
        'viewports'           => array(
            'large'  => '',
            'medium' => null,
            'small'  => 'mobile-',
            'mini'   => null,
        ),
        // Width of labels for horizontal forms expressed as viewport => grid columns
        'labelWidths'         => array(
            'large' => 2,
            'small' => 4,
        ),
        // Classes to be applied to wrapped labels in horizontal forms
        'wrappedLabelClasses' => array('right', 'inline'),
        // HTML markup and classes used by Foundation 3 for icons
        'icon'                => array(
            'tag'    => 'i',
            'set'    => null,
            'prefix' => 'fi',
        ),
        // CSS for inline validation errors
        // should work for Zurb 2 and 3
        'error_classes'       => array('class' => 'alert-box alert error'),
    ),


);
