#!/bin/sh

cd ../media/src

# DEFAULTS
CLOSURE="/usr/local/closure_compiler/compiler.jar"
JSDOC="/usr/local/jsdoc/jsdoc"
CMD=$1

MAIN_FILE="../js/jquery.dataTables.js"
MIN_FILE="../js/jquery.dataTables.min.js"
VERSION=$(grep " * @version     " DataTables.js | awk -F" " '{ print $3 }')

echo ""
echo "  DataTables build ($VERSION)"
echo ""


IFS='%'

cp DataTables.js DataTables.js.build

echo "  Building main script"
grep "require(" DataTables.js.build > /dev/null
while [ $? -eq 0 ]; do
	REQUIRE=$(grep "require(" DataTables.js.build | head -n 1)

	SPACER=$(echo ${REQUIRE} | cut -d r -f 1)
	FILE=$(echo ${REQUIRE} | sed -e "s#^.*require('##g" -e "s#');##")
	DIR=$(echo ${FILE} | cut -d \. -f 1)

	sed "s#^#${SPACER}#" < ${DIR}/${FILE} > ${DIR}/${FILE}.build

	sed -e "/${REQUIRE}/r ${DIR}/${FILE}.build" -e "/${REQUIRE}/d" < DataTables.js.build > DataTables.js.out
	mv DataTables.js.out DataTables.js.build

	rm ${DIR}/${FILE}.build

	grep "require(" DataTables.js.build > /dev/null
done

mv DataTables.js.build $MAIN_FILE


if [ "$CMD" != "debug" ]; then
	if [ "$CMD" = "jshint" -o "$CMD" = "" -o "$CMD" = "cdn" ]; then
		echo "  JSHint"
		jshint $MAIN_FILE --config ../../scripts/jshint.config
		if [ $? -ne 0 ]; then
			echo "    Errors occured - exiting"
			exit 1
		else
			echo "    Pass" 
		fi
	fi

	if [ "$CMD" = "compress" -o "$CMD" = "" -o "$CMD" = "cdn" ]; then
		echo "  Minification"
		echo "/*
 * File:        jquery.dataTables.min.js
 * Version:     $VERSION
 * Author:      Allan Jardine (www.sprymedia.co.uk)
 * Info:        www.datatables.net
 * 
 * Copyright 2008-2012 Allan Jardine, all rights reserved.
 *
 * This source file is free software, under either the GPL v2 license or a
 * BSD style license, available at:
 *   http://datatables.net/license_gpl2
 *   http://datatables.net/license_bsd
 * 
 * This source file is distributed in the hope that it will be useful, but 
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
 * or FITNESS FOR A PARTICULAR PURPOSE. See the license files for details.
 */" > $MIN_FILE 

		java -jar $CLOSURE --js $MAIN_FILE >> $MIN_FILE
		echo "    Min JS file size: $(ls -l $MIN_FILE | awk -F" " '{ print $5 }')"
	fi

	if [ "$CMD" = "docs" -o "$CMD" = "" ]; then
		echo "  Documentation"
		$JSDOC -d ../../docs -t JSDoc-DataTables $MAIN_FILE
	fi

	if [ "$CMD" = "cdn" ]; then
		echo "  CDN"
		if [ -d ../../cdn ]; then
			rm -Rf ../../cdn
		fi
		mkdir ../../cdn
		mkdir ../../cdn/css
		cp $MAIN_FILE ../../cdn
		cp $MIN_FILE ../../cdn
		cp ../css/jquery.dataTables.css ../../cdn/css
		cp ../css/jquery.dataTables_themeroller.css ../../cdn/css
		cp -r ../images ../../cdn/
		rm ../../cdn/images/Sorting\ icons.psd
	fi
fi


# Back to DataTables root dir
cd ../..

#
# Packaging files
#
cat <<EOF > package.json
{
	"name": "DataTables",
	"version": "${VERSION}",
	"title": "DataTables",
	"author": {
		"name": "Allan Jardine",
		"url": "http://sprymedia.co.uk"
	},
	"licenses": [
		{
			"type": "BSD",
			"url": "http://datatables.net/license_bsd"
		},
		{
			"type": "GPLv2",
			"url": "http://datatables.net/license_gpl2"
		}
	],
	"dependencies": {
		"jquery": "1.4 - 1.8"
	},
	"description": "DataTables enhances HTML tables with the ability to sort, filter and page the data in the table very easily. It provides a comprehensive API and set of configuration options, allowing you to consume data from virtually any data source.",
	"keywords": [
		"DataTables",
		"DataTable",
		"table",
		"grid",
		"filter",
		"sort",
		"page",
		"internationalisable"
	],
	"homepage": "http://datatables.net"
}
EOF

cat <<EOF > component.json
{
	"name": "DataTables",
	"version": "${VERSION}",
	"main": [
		"./media/js/jquery.dataTables.js",
		"./media/css/jquery.dataTables.css",
	],
	"dependencies": {
		"jquery": "~1.8.0"
	}
}
EOF


echo "  Done\n"


