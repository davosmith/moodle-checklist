<?php

/**
 * OUTCOMES CSV FILE
 * Separator ';'
 * outcome_name;outcome_shortname;outcome_description;scale_name;scale_items;scale_description
 * "C2i2e-2011 A.1-1 :: Identifier les personnes ressources Tic et leurs rôles respectifs (...)";A.1-1;"Identifier les personnes ressources Tic et leurs rôles respectifs au niveau local, régional et national.";"Item référentiel";"Non pertinent,Non validé,Validé";"Ce barème est destiné à évaluer l'acquisition des compétences du module référentiel."
 *
 * Skills repositoy outcome_name has to match  '/(.*)::(.*)/i' regular expression
 * That's mandatory
 * @author  Jean Fruitet <jean.fruitet@univ-nantes.fr>
 * @package mod/checklist
 */


$separator_outcomes = ';';

// fieldname => output string
$fields_outcomes = array('outcome_name' => 'Outcome name',
                'outcome_shortname' => 'Outcome shortname',
                'outcome_description' => 'Outcome Description',
                'scale_name' => 'Scale name [Item référentiel]',
                'scale_items' => 'Item Values [Non pertinent, Non validé, Validé]',
                'scale_description' => 'Scale description [Ce barème est destiné à évaluer l\'acquisition des compétences du module référentiel.]');


/**
 * Extract skill repository and competency codes from a displaytext field
 * outcome_name;outcome_shortname;outcome_description;scale_name;scale_items;scale_description
 * "C2i2e-2011 A.1-1 :: Identifier les personnes ressources Tic et leurs rôles respectifs (...)";A.1-1;"Identifier les personnes ressources Tic et leurs rôles respectifs au niveau local, régional et national.";"Item référentiel";"Non pertinent,Non validé,Validé";"Ce barème est destiné à évaluer l'acquisition des compétences du module référentiel."
 *  |          |        | description
 *  |          |     ^  separator 2 '::'
 *            ^ separator 1 ' '
 *  |          | competence_code
 *  | referentiel_code
 * @imput displaytext string
 * @output object
 **/

function get_outcome_code($a_text, $referentielcode=''){
// extract skill repository code and competency code from an outcome_name field
    $item_outcome = new stdClass;
    if (!empty($a_text)){ 
        if (preg_match('/(.*)::(.*)/i', $a_text, $matches)){
            if ($matches[1]){
                if ($keywords = preg_split("/[\s]+/",$matches[1],-1,PREG_SPLIT_NO_EMPTY)){
                    if ($keywords[0] && $keywords[1]){
                        if ($referentielcode){
                            $item_outcome->code_referentiel=$referentielcode;
                        }else{
                            $item_outcome->code_referentiel=trim($keywords[0]);
                        }
                        $item_outcome->code_competence=trim($keywords[1]);
                    }
                    else if ($keywords[0]){
                        if ($referentielcode){
                            $item_outcome->code_referentiel=$referentielcode;
                        }else{
                            $item_outcome->code_referentiel='REF_'.get_string('a_completer', 'checklist');
                        }
                        $item_outcome->code_competence=trim($keywords[0]);                       
                    }
                    else{
                        return NULL;
                    }
                }
            }
            else{
                return NULL;
            }
            
            if (!empty($matches[2])){
                $item_outcome->description=trim($matches[2]);
            }
            else{
                $item_outcome->description=get_string('a_completer', 'checklist');
            }
            $item_outcome->outcome=$item_outcome->code_referentiel.' '. $item_outcome->code_competence.' :: '.$item_outcome->description;
        }
        
    }
    /*
    print_r($item_outcome);
    echo "<br>importexportoutcome.php :: 84 ::EXIT\n";
    exit;
    */
    return $item_outcome;
}

/**
 * uses  get_outcome_code()
 * @imput string
 * @output object
 **/
function get_outcome_code_from_item($item, $referentielcode='', $useitemid=false){
    if (!empty($item) && !empty($item->checklist) && !empty($item->displaytext)){
        $item_outcome=get_outcome_code($item->displaytext, $referentielcode);
        /*
        // DEBUG
        echo "<br>INPUT\n";
        print_object($item_outcome);
        echo "<br>\n";
        */
        if (empty($item_outcome) || empty($item_outcome->code_competence)){
            $chaine=trim($item->displaytext);
            $chaine1=str_replace(array("\n","\r","\r\n"),'. ',$chaine);
            $chaine=str_replace(array("\n","\r","\r\n"),' ',$chaine);
            $keywords = preg_split("/[\s]+/",$chaine,-1,PREG_SPLIT_NO_EMPTY);
            if ($keywords){
                if (!empty($keywords[1])){
                    if ($referentielcode){
                        $item_outcome->code_referentiel=$referentielcode;
                    }else{
                        $item_outcome->code_referentiel=trim($keywords[0]);
                    }
                    if ($useitemid){
                        $item_outcome->code_competence='#ID_'.$item->id;
                    }
                    else{
                        $item_outcome->code_competence=trim($keywords[1]);
                    }
                }
                else if (!empty($keywords[0])){
                    if ($useitemid){
                        $item_outcome->code_competence='#ID_'.$item->id;
                    }
                    else{
                        $item_outcome->code_competence=trim($keywords[0]);
                    }
                    if ($referentielcode){
                        $item_outcome->code_referentiel=$referentielcode;
                    }
                    else{
                        $item_outcome->code_referentiel='REF_'.$item->checklist;
                    }
                }
            }
            else{
                if ($useitemid){
                    $item_outcome->code_competence='#ID_'.$item->id;
                }
                else{
                    $item_outcome->code_competence=$chaine;
                }

                if ($referentielcode){
                    $item_outcome->code_referentiel=$referentielcode;
                }
                else{
                    $item_outcome->code_referentiel='REF_'.$item->checklist;
                }
            }
            $item_outcome->description=$chaine1.' ['.get_string('a_completer', 'checklist').']';
            $item_outcome->outcome=$item_outcome->code_referentiel.' '. $item_outcome->code_competence.' :: '.$item_outcome->description;
        }
        /*
        // DEBUG
        echo "<br>SORTIE\n";
        print_object($item_outcome);
        echo "<br>\n";
        */
        return $item_outcome;
    }
    return NULL;
}
               
