<?php
/**
dash-smarty-ja

Copyright (c) 2014 T.Takamatsu <takamatsu@tactical.jp>

This software is released under the MIT License.
http://opensource.org/licenses/mit-license.php
*/


//----------------------------------------
// Config
//----------------------------------------

$lang = !empty($argv[1]) ? $argv[1] : 'ja'; // default is ja
$ver  = '3.1';

$type = [
	'bc'		=> [
		'bc.html#bc.class'					=> 'Class'
	],
	'language'	=> [
		'language.variables.smarty.html#'	=> 'Variable',
		'language.modifier.'				=> 'Modifier',
		'language.modifiers.html#'			=> 'Modifier',
		'language.function.'				=> 'Function',
		'language.builtin.functions.html#'	=> 'Function',
		'language.custom.functions.html#'	=> 'Function',
	],
	'smarty'	=> [
		'smarty.constants.html#'			=> 'Constant',
	],
];


//----------------------------------------
//
// Main process
//
//----------------------------------------

echo "\nStart build Smarty docset ...\n";

try {
	exec_ex('rm -rf Smarty.docset/Contents/Resources/');

	if (!mkdir('Smarty.docset/Contents/Resources/', 0777, true)) {
		do_exception(__LINE__);
	}
	exec_ex('mv ' . __DIR__ . "/smarty-documentation/docs/manual-{$lang} " . __DIR__ . '/Smarty.docset/Contents/Resources/Documents/');

	// gen Info.plist
	$ret = file_put_contents(__DIR__ . '/Smarty.docset/Contents/Info.plist', <<<ENDE
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
	if ($ret === false) {
		do_exception(__LINE__);
	}
	if (
		!copy(__DIR__ . '/icon.png',  __DIR__ . '/Smarty.docset/icon.png') ||
		!copy(__DIR__ . '/style.css', __DIR__ . '/Smarty.docset/Contents/Resources/Documents/style.css')
	) {
		do_exception(__LINE__);
	}
}
catch (Exception $e) {
	throw new Exception("\nSmarty docset build failed.\nFix error and try again.\n\n", -1, $e);
}
finally {
}

$dom = new DomDocument;
@$dom->loadHTMLFile(__DIR__ . '/Smarty.docset/Contents/Resources/Documents/index.html');
$divList = $dom->getElementsByTagName('div');

$db = new sqlite3(__DIR__ . '/Smarty.docset/Contents/Resources/docSet.dsidx');
$db->query('CREATE TABLE searchIndex(id INTEGER PRIMARY KEY, name TEXT, type TEXT, path TEXT)');
$db->query('CREATE UNIQUE INDEX anchor ON searchIndex (name, type, path)');

// add links from examples
$seek = false;

foreach ($divList as $tag) {
	if (strtolower($tag->getAttribute('class')) != 'list-of-examples') {
		continue;
	}
	$seek = $tag;
	break;
}

if ($seek) {
	$tags = $seek->getElementsByTagName('a');

	foreach ($tags as $tag) {
		$href = $tag->getAttribute('href');
		$str  = substr($href, 0, 6);

		if ($str[0] == '.') {
			continue;
		}
		if ($str == 'https:' || !strncmp($str, 'http:', 5)) {
			continue;
		}

		$name = trim(preg_replace('#\s+#u', ' ', str_replace(["\r\n", "\n", "\r"], '', $tag->nodeValue)));

		if (empty($name)) {
			continue;
		}
		$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES ('{$name}', 'Sample', '{$href}')");
	}
}

// add links from table of contents
$seek = false;

foreach ($divList as $tag) {
	if (strtolower($tag->getAttribute('class')) != 'toc') {
		continue;
	}
	$seek = $tag;
	break;
}

if ($seek) {
	$tags = $seek->getElementsByTagName('a');

	foreach ($tags as $tag) {
		$href = $tag->getAttribute('href');
		$str  = substr($href, 0, 6);

		if ($str[0] == '.') {
			continue;
		}
		if ($str == 'https:' || !strncmp($str, 'http:', 5)) {
			continue;
		}

		$name = str_replace(["\r\n", "\n", "\r"], '', $tag->nodeValue);
		$name = trim(preg_replace('#\s+#u', ' ', preg_replace('#^[A-Z0-9-]+\.#u', '', $name)));

		if (empty($name)) {
			continue;
		}

		// set types
		$str   = explode('.', $href);
		$class = '';

		if ($str[0] == 'variable') {
			// Smarty class property
			if ($name[0] == '$') {
				$name = substr($name, 1);
			}
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
				if ($name[0] == '$') {
					$name = substr($name, 1);
				}
				$name  = "Smarty::{$name}"; // override
				$class = 'Property';
			}
			// Smarty class method
			else {
				if (substr($name, -2) == '()') {
					$name = substr($name, 0, -2);
				}
				$name  = "Smarty::{$name}"; // override
				$class = 'Method';
			}
		}
		else if (!empty($type[$str[0]])) {
			$str = $type[$str[0]];

			foreach ($str as $key => $val) {
				if (strncmp($href, $key, strlen($key))) {
					continue;
				}

				if (is_array($val)) {
					$name  = $val['name']; // override
					$class = $val['type'];
				}
				else {
					$class = $val;
				}
				break;
			}
		}

		if (!$class) {
			$class = 'Guide';
		}
		$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES ('{$name}', '{$class}', '{$href}')");
	}
}

// add css
echo "\nAdding stylesheet tag to html files ...\n";

foreach (glob(__DIR__ . '/Smarty.docset/Contents/Resources/Documents/*.html') as $file) {
	if (!$dom = file_get_contents($file)) {
		continue;
	}
	file_put_contents($file, str_replace(
		"\n</head>\n",
		"\n<link rel=\"stylesheet\" type=\"text/css\" href=\"style.css\">\n</head>\n",
		$dom
	));
}

echo "\nSmarty docset created !\n\n";


//----------------------------------------
// Helper functions
//----------------------------------------

// Throw Exception
function do_exception($line, $code = -1) {
	throw new Exception("Error at line: {$line}", $code);
}

// Exec with exception logic
function exec_ex($cmd) {
	if (($cmd = strval($cmd)) === '') {
		do_exception(__LINE__);
	}

	$out = null;
	$ret = 0;
	exec($cmd, $out, $ret);

	if ($ret) {
		do_exception(__LINE__, $ret);
	}

	return true;
}


