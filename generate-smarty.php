<?php

$lang = !empty($argv[1]) ? $argv[1] : 'ja'; // default is ja
$ver  = '3.1';

$type = array(
	'bc'		=> array('bc.html#bc.class'	=> 'Class'),
	'language'	=> array(
		'language.variables.smarty.html#'	=> 'Variable',
		'language.modifier.'				=> 'Modifier',
		'language.modifiers.html#'			=> 'Modifier',
		'language.function.'				=> 'Function',
		'language.builtin.functions.html#'	=> 'Function',
		'language.custom.functions.html#'	=> 'Function',
	),
	'smarty'	=> array(
		'smarty.constants.html#'			=> 'Constant',
	),
);


exec('rm -rf Smarty.docset/Contents/Resources/');
exec('mkdir -p Smarty.docset/Contents/Resources/');
exec('mv ' . __DIR__ . "/documentation/manual-{$lang} " . __DIR__ . '/Smarty.docset/Contents/Resources/Documents/');

file_put_contents(__DIR__ . '/Smarty.docset/Contents/Info.plist', <<<ENDE
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>CFBundleIdentifier</key>
	<string>smarty-{$lang}</string>
	<key>CFBundleName</key>
	<string>Smarty {$ver}-{$lang}</string>
	<key>DocSetPlatformFamily</key>
	<string>smarty</string>
	<key>isDashDocset</key>
	<true/>
	<key>dashIndexFilePath</key>
	<string>index.html</string>
</dict>
</plist>
ENDE
);
copy(__DIR__ . '/icon.png', __DIR__ . '/Smarty.docset/icon.png');

$dom = new DomDocument;
@$dom->loadHTMLFile(__DIR__ . '/Smarty.docset/Contents/Resources/Documents/index.html');
$divList = $dom->getElementsByTagName('div');

$db = new sqlite3(__DIR__ . '/Smarty.docset/Contents/Resources/docSet.dsidx');
$db->query('CREATE TABLE searchIndex(id INTEGER PRIMARY KEY, name TEXT, type TEXT, path TEXT)');
$db->query('CREATE UNIQUE INDEX anchor ON searchIndex (name, type, path)');

// add links from examples
$seek = false;

foreach ($divList as $tag) {
	if (strtolower($tag->getAttribute('class')) != 'list-of-examples') continue;
	$seek = $tag;
	break;
}

if ($seek) {
	$tags = $seek->getElementsByTagName('a');

	foreach ($tags as $tag) {
		$href = $tag->getAttribute('href');
		$str  = substr($href, 0, 6);
		if ($str[0] == '.') continue;
		if ($str == 'https:' || !strncmp($str, 'http:', 5)) continue;

		$name = trim(preg_replace('#\s+#u', ' ', str_replace(array("\r\n", "\n", "\r"), '', $tag->nodeValue)));
		if (empty($name)) continue;

		$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES ('{$name}', 'Sample', '{$href}')");
	}
}

// add links from table of contents
$seek = false;

foreach ($divList as $tag) {
	if (strtolower($tag->getAttribute('class')) != 'toc') continue;
	$seek = $tag;
	break;
}

if ($seek) {
	$tags = $seek->getElementsByTagName('a');

	foreach ($tags as $tag) {
		$href = $tag->getAttribute('href');
		$str  = substr($href, 0, 6);
		if ($str[0] == '.') continue;
		if ($str == 'https:' || !strncmp($str, 'http:', 5)) continue;

		$name = str_replace(array("\r\n", "\n", "\r"), '', $tag->nodeValue);
		$name = trim(preg_replace('#\s+#u', ' ', preg_replace('#^[A-Z0-9-]+\.#u', '', $name)));
		if (empty($name)) continue;

		// set types
		$str = explode('.', $href);

		if ($str[0] == 'variable') {
			// Smarty class property
			if ($name[0] == '$') $name = substr($name, 1);
			$name  = "Smarty::{$name}"; // override
			$class = 'Property';
		}
		else if ($str[0] == 'api') {
			// Guide
			if ($href == 'api.functions.html' || $href == 'api.variables.html') {
				$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES ('Smarty', 'Class', '{$href}')");
				$class = 'Guide';
			}
			// Smarty class property
			else if (!strncmp($href, 'api.variables.html#', 19)) {
				if ($name[0] == '$') $name = substr($name, 1);
				$name  = "Smarty::{$name}"; // override
				$class = 'Property';
			}
			// Smarty class method
			else {
				if (substr($name, -2) == '()') $name = substr($name, 0, -2);
				$name  = "Smarty::{$name}"; // override
				$class = 'Method';
			}
		}
		else if (!empty($type[$str[0]])) {
			$str = $type[$str[0]];

			foreach ($str as $key => $val) {
				if (strncmp($href, $key, strlen($key))) continue;
				if (is_array($val)) {
					$name  = $val['name']; // override
					$class = $val['type'];
				}
				else {
					$class = $value;
				}
				break;
			}
		}
		else {
			$class = 'Guide';
		}

		$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES ('{$name}', '{$class}', '{$href}')");
	}
}

