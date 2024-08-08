<?php
/* Copyright (C) 2024 Vincent Coulon
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    depensestype/lib/depensestype.lib.php
 * \ingroup depensestype
 * \brief   Library files with common functions for Depensestype
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function depensestypeAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("depensestype@depensestype");
	complete_head_from_modules($conf, $langs, null, $head, $h, 'depensestype@depensestype');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'depensestype@depensestype', 'remove'); 

	return $head;
}

// return array[]
function get_depense_types()
{
	$depenses_types = array(
		"0" => "Dépense par défaut", //valeur par défaut
		"1" => "Dépense fixe", //loyer, assurances, abonnements....
		"2" => "Dépense exceptionnelle", //domaines, extincteurs, frais annuels
		"3" => "Dépense pour stock", //materiel acheté pour stock 
		"4" => "Dépense pour commande", //materiel acheté pour commande client
		"5" => "Dépense pour outils/consommables", //materiel acheté pour le magasin
	);
	return $depenses_types;
}

//from type string and date string "YYYY-mm", returns array[$year . "-" . $month] => $value;
function fetch_depenses_by_type($type, $months)
{
	global $conf, $db;

	//Fix if only one month provided without array
	if (!is_array($months))
		$months = array($months);

	$total = array();
	
	for ($i = 0; $i < count($months); $i++) {
		$month = $months[$i];

		//check if format is OK
		$split = explode('-', $month);
		$year = $split[0];
		$month = $split[1];
		if(count($split) !== 2)
			return;
		
		if(!is_numeric($year))
			return;
		
		if(!is_numeric($month))
			return;

		$sql = "";

		if ($conf->global->ACCOUNTING_MODE === 'RECETTES-DEPENSES') //dépensé
		{
			$sql .= "SELECT IF( SUM(llx_facture_fourn_det.total_ttc) IS NULL,0,SUM(llx_facture_fourn_det.total_ttc)) AS total";
			$sql .= " FROM llx_facture_fourn_det_extrafields, llx_facture_fourn_det, llx_facture_fourn, llx_facture_fourn_extrafields";
			$sql .= " WHERE llx_facture_fourn.rowid = llx_facture_fourn_det.fk_facture_fourn AND llx_facture_fourn_det_extrafields.fk_object = llx_facture_fourn_det.rowid AND llx_facture_fourn.rowid = llx_facture_fourn_extrafields.fk_object";
			$sql .= " AND llx_facture_fourn_det_extrafields.depense_type = '" . $type . "'";
			$sql .= " AND MONTH(llx_facture_fourn.date_closing) = " . $month;
			$sql .= " AND YEAR(llx_facture_fourn.date_closing) = " . $year;
			$sql .= " AND llx_facture_fourn.paye = 1";
		}
		else if ($conf->global->ACCOUNTING_MODE === 'CREANCES-DETTES') //facturé
		{
			$sql .= "SELECT IF( SUM(llx_facture_fourn_det.total_ttc) IS NULL,0,SUM(llx_facture_fourn_det.total_ttc)) AS total";
			$sql .= " FROM llx_facture_fourn_det_extrafields, llx_facture_fourn_det, llx_facture_fourn, llx_facture_fourn_extrafields";
			$sql .= " WHERE llx_facture_fourn.rowid = llx_facture_fourn_det.fk_facture_fourn AND llx_facture_fourn_det_extrafields.fk_object = llx_facture_fourn_det.rowid AND llx_facture_fourn.rowid = llx_facture_fourn_extrafields.fk_object";
			//$sql .= " AND llx_facture_fourn.paye = 1";
			//$sql .= " AND llx_facture_fourn_extrafields.ssi_ref=''";
			//check for facture abandonnée ou brouillon
			$sql .= " AND llx_facture_fourn.fk_statut != 0";
			$sql .= " AND llx_facture_fourn.fk_statut != 3";
			$sql .= " AND llx_facture_fourn_det_extrafields.depense_type = '" . $type . "'";
			$sql .= " AND MONTH(llx_facture_fourn.datef) = " . $month;
			$sql .= " AND YEAR(llx_facture_fourn.datef) = " . $year;
			// exlure la ligne SSI du mois precedant si option est 0
			if ($conf->global->MICROURSSAF_TOTAL_SSI_OPTION === "0") {
				$sql .= " AND (llx_facture_fourn.fk_fac_rec_source != " . $conf->global->MICROURSSAF_DECLARATION_FACT_MODEL;
				$sql .= " OR llx_facture_fourn.fk_fac_rec_source IS NULL)";
			}
		}

		/* get total spent by type, month, year
"'SELECT IF( SUM(llx_facture_fourn.total_ttc) IS NULL,0,SUM(llx_facture_fourn.total_ttc)) AS total
FROM llx_facture_fourn_det_extrafields, llx_facture_fourn_det, llx_facture_fourn, llx_facture_fourn_extrafields
WHERE llx_facture_fourn.rowid = llx_facture_fourn_det.fk_facture_fourn AND llx_facture_fourn_det_extrafields.fk_object = llx_facture_fourn_det.rowid AND llx_facture_fourn.rowid = llx_facture_fourn_extrafields.fk_object
AND llx_facture_fourn.paye = 1
AND llx_facture_fourn_extrafields.ssi_ref=''
AND llx_facture_fourn_det_extrafields.depense_type=''
AND MONTH(llx_facture_fourn.date_closing) = 8
AND YEAR(llx_facture_fourn.date_closing) = 2024 LIMIT 100'"
		*/

		$res = $db->query($sql);
		
		if ($res)
		{
			$rec = $db->fetch_object($res);
			$value = $rec->total;
			$value = round($value, 2);
			$total = $value;
		}
		else
		{
			dol_print_error($db);
		}
	}
	return $total;
}
