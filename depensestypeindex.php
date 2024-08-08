<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       depensestype/depensestypeindex.php
 *	\ingroup    depensestype
 *	\brief      Home page of depensestype top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
include_once DOL_DOCUMENT_ROOT . '/core/boxes/modules_boxes.php';
require_once DOL_DOCUMENT_ROOT . '/custom/depensestype/lib/depensestype.lib.php';

// Load translation files required by the page
$langs->loadLangs(array("depensestype@depensestype"));

$action = GETPOST('action', 'aZ09');

// Security check
// if (! $user->rights->depensestype->myobject->read) {
// 	accessforbidden();
// }
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$max = 5;
$now = dol_now();


/*
 * Actions
 */

// None


/*
 * View
 */

$box = new ModeleBoxes($db, '');

$form = new Form($db);
$formfile = new FormFile($db);

$table = array();

$year_selected = GETPOST('year') !== "" ? GETPOST('year') : date('Y'); //select this year OR the post data
$month_offset = GETPOST('month_offset') !== "" ? GETPOST('month_offset') : 0; //select this year OR the post data

$results = array();

//months columns
$columns = array("");

$depenses_types = get_depense_types();

$lines = array();
$lines[-1] = "empty";
foreach ($depenses_types as $depenses_type_id => $depenses_type_name) {
	$lines[] = $depenses_type_name;
}
$lines[] = "Total";
//var_dump($lines);

$grand_total = 0;
for ($month_number = 1; $month_number <= 13; $month_number++)
{
	$month_total = 0;
	if ($month_number <= 12)
	{
		$month = date('Y-m', mktime(0,0,0,$month_number + $month_offset, 1, $year_selected));
		$year = explode('-', $month)[0];
		$monthname = get_month_name($month_number + $month_offset);

		$columns[] = $monthname . " " . $year;

		for($l=0; $l <= count($lines); $l++) //pupulate lines
		{
			//set default value
			$results[$l][$month_number] = 0;
			if(!isset($results[$l][13]))
				$results[$l][13] = 0;

			// Fill the table
			$data = fetch_depenses_by_type($l . "", $month);
			$results[$l][$month_number] = $data;
			$results[$l][13] += $data;
			$month_total += $data;
			if ($l == count($lines)-2)
				$results[$l][$month_number] = $month_total;
		}
		$grand_total += $month_total;
	}
	else
	{
		$columns[] = "Total";
		$results[count($lines)-2][13] = $grand_total;
	}
}

for($l=0; $l< count($lines); $l++) //pupulate lines
{
	for($c=0; $c<count($columns); $c++) //populate columns
	{
		if ($l === 0) //title line
		{
			$table[$l][$c] = array( // First line th
				'tr' => 'class="right "',
				'td' => '',
				'text' => '',
				'textnoformat' => $columns[$c],
				'maxlength' => 0,
				'asis' => false,
				'asis2' => true
			);
		}
		elseif ($c === 0) //title column
		{
			$table[$l][$c] = array(
				'tr' => 'class="left oddeven"',
				'text' => $langs->trans($lines[$l-1])
			);
		}
		else
		{
			$table[$l][$c] = array( // td
				'td' => 'class="right" title="' . $langs->trans($lines[$l-1]) . '"',
				'text' => $results[$l-1][$c] . '€'
			);
		}
	}
}

llxHeader("", $langs->trans("DepenseTypeArea"));

print '<div class="fichecenter">';

//display table for a year
$yearLink = dol_buildpath("/custom/depensestype/depensestypeindex.php", 1) . "?token=" . newToken();

$yearselecytor = '<span style="float:right;">
		<ul style="margin-top: 0px; margin-bottom: 0px; padding-left: 10px;">
			<li class="pagination"><a accesskey="p" title="Année précédante<br>Raccourci clavier ALT + p" class="classfortooltip" href="' . $yearLink . '&year=' . ($year_selected - 1) . '"><i class="fa fa-step-backward"></i></a></li>
			<li class="pagination"><a accesskey="o" title="Mois précédant<br>Raccourci clavier ALT + o" class="classfortooltip" href="' . $yearLink . '&year=' . $year_selected . '&month_offset=' . ($month_offset - 1) . '"><i class="fa fa-backward"></i></a></li>
			<span> ' . $year_selected . ' </span>
			<li class="pagination"><a accesskey="b" title="Mois suivante<br>Raccourci clavier ALT + b" class="classfortooltip" href="' . $yearLink . '&year=' . $year_selected . '&month_offset=' . ($month_offset + 1) . '"><i class="fa fa-forward"></i></a></li>
			<li class="pagination"><a accesskey="n" title="Année suivante<br>Raccourci clavier ALT + n" class="classfortooltip" href="' . $yearLink . '&year=' . ($year_selected + 1) . '"><i class="fa fa-step-forward"></i></a></li>
		</ul></span>';

$text = '<span style="float:left;">Dépenses par type année ' .  $year_selected . '</span>'  . $yearselecytor;

$info_box_head = array(
	'text' => $text,
	'subclass' => 'center',
	'limit' => 0,
	'graph' => false
);

$box->showBox($info_box_head, $table, 0);

print '</div>';

// End of page
llxFooter();
$db->close();
