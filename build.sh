#!/bin/sh

# Config
LANG=ja

PHP=/usr/bin/php
SVN=/usr/local/bin/svn

STYLESHEETS_DIR=/usr/local/share/docbook-xsl
FOP=/usr/local/bin/fop
XMLTO=/usr/local/bin/xmlto

# Build Smarty manual
rm -rf documentation
${SVN} checkout http://smarty-php.googlecode.com/svn/trunk/documentation/
cd documentation
${SVN} checkout http://smarty-php.googlecode.com/svn/branches/Smarty2Dev/docs/dtds/

mv Makefile-dist Makefile
make LANG=${LANG} STYLESHEETS_DIR=${STYLESHEETS_DIR} FOP=${FOP} XMLTO=${XMLTO} html

# Packing for Dash
cd ..
${PHP} generate-smarty.php ${LANG}

rm -rf documentation
