Datatables-Bootstrap3
=====================

Quick Start
-------------------------------
Copy these files and the images inside assets to your project and include them in the page.

1. assets/css/datatables.css
2. assets/js/datatables.js
3. assets/images/*  

Please note : If you copy the images to a folder other than images in your project, be sure to update the image path in the datatables.css file.

Pagination types
-------------------------------

1. Normal:

		//Default Type
        $('.datatable').dataTable(); 
		$('.datatable').dataTable({"sPaginationType": "bs_normal"});	

2. Two Buttons:

        $('.datatable').dataTable({"sPaginationType": "bs_two_button"});
		
2. Four Buttons:

        $('.datatable').dataTable({"sPaginationType": "bs_four_button"});
		
2. Full :

        $('.datatable').dataTable({"sPaginationType": "bs_full"});

Note: These are extended pagination types without modifying the existing pagination types.
		
License
-------------------------------
[Included as per request of an user in github]
This is an extension of datatables and shares some code with the existing jquery datatables. License of datatables might apply to this plugin as well by way of inheritance.

As far as license for this plugin goes, here is the gist:

1. No attribution in any form is needed.
2. I accept no responsibility if this plugin breaks / cause any issue in your project.
3. Do not sell this plugin by itself as a commercial project however you can include this freely as part of any project (commercial or otherwise).

