#!/bin/sh

#
# Config
#

# LNAG (en / ja / ru / de / es / fr / it / pt_BR)
LANG=ja

# Windows (MSYS)
#STYLESHEETS_DIR=/usr/share/xml/docbook/xsl-stylesheets-1.78.1
#XMLTO=/usr/bin/xmlto
#FOP=/usr/local/bin/fop

# Mac (Homebrew)
export XML_CATALOG_FILES="/usr/local/etc/xml/catalog"
STYLESHEETS_DIR=/usr/local/share/docbook-xsl
XMLTO=/usr/local/bin/xmlto
FOP=/usr/local/opt/fop/bin/fop


# Build Smarty manual
rm -rf smarty-documentation
git clone https://github.com/smarty-php/smarty-documentation.git
cd smarty-documentation/docs

cp -a Makefile-dist Makefile
make LANG=${LANG} STYLESHEETS_DIR=${STYLESHEETS_DIR} FOP=${FOP} XMLTO=${XMLTO} html

# Packing for Docset
cd ../..
php generate-smarty.php ${LANG}

rm -rf smarty-documentation
