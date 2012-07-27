<?php

// This file is part of the Checklist plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

$string['addcomments'] = 'Ajouter des commentaires';

$string['additem'] = 'Ajouter';
$string['additemalt'] = 'Ajouter un nouvel item &agrave; la liste';
$string['additemhere'] = 'Ins&eacute;rer le nouvel item apr&egrave;s celui-ci';
$string['addownitems'] = 'Ajouter vos propres items';
$string['addownitems-stop'] = 'Arr&ecirc;t d\'ajout d\'items';

$string['allowmodulelinks'] = 'Autoriser les liens vers les &eacute;l&eacute;ments';

$string['anygrade'] = 'Tout';
$string['autopopulate'] = 'Montrer les &eacute;l&eacute;ments du cours dans la Liste des t&acirc;ches';
$string['autopopulate_help'] = 'Cela ajoutera automatiquement une liste de toutes les ressources et les activit&eacute;s dans le cadre actuel dans la liste. <br />
Cette liste sera mise &agrave; jour avec tous les changements en cours, lorsque vous visitez la page "Modifier" pour la Liste des t&acirc;ches. <br />
Les items peuvent &ecirc;tre cach&eacute;s dans la liste, en cliquant sur l\'ic&ocirc;ne "Cacher" &agrave; c&ocirc;t&eacute; d\'eux.<br />
Pour supprimer les items automatiques de la liste, modifier cette option en cliquant sur "Non", puis cliquez sur "Supprimer des &eacute;l&eacute;ments de cours" sur la page "Modifier".';
$string['autoupdate'] = 'Cochez quand les modules sont complets';
$string['autoupdate_help'] = 'Cela va automatiquement cocher les &eacute;l&eacute;ments de votre Liste des t&acirc;ches lorsque vous terminez l\'activit&eacute; concern&eacute;e dans le cours. <br />
"Finir" une activit&eacute; varie d\'une activit&eacute; &agrave; l\'autre - "voir" une ressource, "envoyer" un quiz ou un fichier, "r&eacute;pondre" &agrave; un forum ou participez &agrave; un chat, etc <br />
Si un suivi de fin de Moodle 2.0 est activ&eacute; pour une activit&eacute; particuli&egrave;re, il sera utilis&eacute; pour les cocher l\'&eacute;l&eacute;ment dans la liste <br />
Pour plus de d&eacute;tails sur la cause exacte qu\'une activit&eacute; peut &ecirc;tre marqu&eacute; comme "achev&eacute;e", demandez &agrave; votre administrateur du site pour regarder dans le fichier "mod / liste / autoupdate.php" <br />
Remarque: cel&agrave; peut prendre jusqu\'&agrave; 60 secondes pour que l\'activit&eacute; d\'un &eacute;tudiant se mette &agrave; jour dans leur Liste des t&acirc;ches';

$string['autoupdatewarning_both'] = 'Il y a des items sur cette liste qui seront automatiquement mis &agrave; jour (comme ceux que les &eacute;tudiants disent "complet"). Cependant, dans le cas d\'une Liste des t&acirc;ches commune "&eacute;tudiant et enseignant", les barres de progression ne seront pas mises &agrave; jour tant qu\'un enseignant accepte les notes attribu&eacute;es.';
$string['autoupdatewarning_student'] = 'Il y a des items sur cette liste qui seront automatiquement mis &agrave; jour (comme ceux que les &eacute;tudiants disent "complet").';
$string['autoupdatewarning_teacher'] = 'La mise &agrave; jour automatique a &eacute;t&eacute; activ&eacute;e pour cette liste, mais ces remarques ne seront pas affich&eacute;e tant que l\'enseignant ne les montre pas.';

$string['canceledititem'] = 'Effacer';

$string['calendardescription'] = 'Cet &eacute;l&eacute;ment a &eacute;t&eacute; ajout&eacute; par la Liste des t&acirc;ches : {$a}';

$string['changetextcolour'] = 'Prochaine couleur de texte';

$string['checkeditemsdeleted'] = 'Items de la Liste des t&acirc;ches supprim&eacute;s';

$string['checklist'] = 'Liste des t&acirc;ches';
$string['pluginadministration'] = 'Administration de la Liste des t&acirc;ches';

$string['checklist:edit'] = 'Cr&eacute;er et &eacute;diter des Liste des t&acirc;ches';
$string['checklist:emailoncomplete'] = 'Recevoir par mail quand c\'est complet';
$string['checklist:preview'] = 'Pr&eacute;visualisation d\'une Liste des t&acirc;ches';
$string['checklist:updatelocked'] = 'Mise &agrave; jour des marques verrouill&eacute;e';
$string['checklist:updateother'] = 'Mise &agrave; jour des marques des Liste des t&acirc;ches des &eacute;tudiants';
$string['checklist:updateown'] = 'Mise &agrave; jour de vos marques des Liste des t&acirc;ches';
$string['checklist:viewreports'] = 'Voir la progression des &eacute;tudiants';

$string['checklistautoupdate'] = 'Autoriser les Liste des t&acirc;ches &agrave; se mettre &agrave; jour automatiquement';

$string['checklistfor'] = 'Liste des t&acirc;ches pour';

$string['checklistintro'] = 'Introduction';
$string['checklistsettings'] = 'Param&egrave;tres';

$string['checks'] = 'Marques';
$string['comments'] = 'Commentaires';

$string['completionpercentgroup'] = 'A cocher obligatoirement';
$string['completionpercent'] = 'Pourcentage d\'items qui doivent &ecirc;tre coch&eacute;s :';

$string['configchecklistautoupdate'] = 'Avant de permettre cela, vous devez faire quelques modifications au code Moodle, merci de voir le "mod / liste / README.txt" pour plus de d&eacute;tails';

$string['confirmdeleteitem'] = 'Etes-vous s&ucirc;r de vouloir effacer d&eacute;finitivement cet item de la Liste des t&acirc;ches?';

$string['deleteitem'] = 'Effacer cet item';

$string['duedatesoncalendar'] = 'Ajouter les dates d\'&eacute;ch&eacute;ance au calendrier';

$string['edit'] = 'Editer la Liste des t&acirc;ches';
$string['editchecks'] = 'Editer les coches';
$string['editdatesstart'] = 'Editer les dates';
$string['editdatesstop'] = 'Arr&ecirc;t de l\&eacute;dition des dates';
$string['edititem'] = 'Editer cet item';

$string['emailoncomplete'] = 'Envoyer un courriel &agrave; l\'enseignant quand la liste des t&acirc;ches est compl&egrave;te';
$string['emailoncomplete_help'] = 'Quand une liste est compl&egrave;te, un courriel de notification est envoy&eacute; &agrave; tous les enseignants du cours. <br />
Un administrateur peut contr&ocirc;ler qui re&ccedil;oit ce courriel en utilisant la capacit&eacute; "mod: check-list / emailoncomplete" - par d&eacute;faut, tous les enseignants et enseignants non &eacute;diteurs ont cette capacit&eacute;.';
$string['emailoncompletesubject'] = 'L\'utilisateur {$a->user} a compl&eacute;t&eacute; sa Liste de t&acirc;ches \'{$a->checklist}\'';
$string['emailoncompletebody'] = 'L\'utilisateur {$a->user} a compl&eacute;t&eacute; sa Liste de t&acirc;ches \'{$a->checklist}\'
Voir la Liste des t&acirc;ches ici :';

$string['export'] = 'Exportation des items';

$string['forceupdate'] = 'Mise &agrave; jour des coches pour les items automatiques';

$string['gradetocomplete'] = 'Evaluation pour terminer';
$string['guestsno'] = 'Vous n\'avez pas la permission de voir cette Liste des t&acirc;ches';

$string['headingitem'] = 'Cet item est une &eacute;tiquette, il n\'y aura pas de case &agrave; cocher &agrave; c&ocirc;t&eacute;';

$string['import'] = 'Import d\'items';
$string['importfile'] = 'Choisir le fichier &agrave; importer';
$string['importfromsection'] = 'Section courante';
$string['importfromcourse'] = 'Tout le cours';
$string['indentitem'] = 'D&eacute;caller l\'item';
$string['itemcomplete'] = 'Termin&eacute;';
$string['items'] = 'Items de la Liste des t&acirc;ches';

$string['linktomodule'] = 'Lien de la ressource ou de l\'activit&eacute;';

$string['lockteachermarks'] = 'Verrouillage des coches de l\'enseignant';
$string['lockteachermarks_help'] = 'Lorsque ce param&egrave;tre est activ&eacute;, une fois qu\'un enseignant a sauv&eacute; une coche "Oui", il ne sera plus possible de changer la valeur. Les utilisateurs ayant la capacit&eacute; "mod / check-list: updatelocked" sera toujours en mesure de changer la coche.';
$string['lockteachermarkswarning'] = 'Remarque: Une fois que vous avez enregistr&eacute; ces coches, il vous sera impossible de changer toutes les coches "Oui"';

$string['modulename'] = 'Liste des t&acirc;ches';
$string['modulenameplural'] = 'Listes des t&acirc;ches';

$string['moveitemdown'] = 'Descendre l\'item';
$string['moveitemup'] = 'Monter l\'item';

$string['noitems'] = 'Pas d\'items dans la Liste des t&acirc;ches';

$string['optionalitem'] = 'Cet item est optionnel';
$string['optionalhide'] = 'Cacher les options des items';
$string['optionalshow'] = 'Montrer les options des items';

$string['percentcomplete'] = 'Items obligatoires';
$string['percentcompleteall'] = 'Tous les items';
$string['pluginname'] = 'Liste des t&acirc;ches';
$string['preview'] = 'Pr&eacute;visualisation';
$string['progress'] = 'Progression';

$string['removeauto'] = 'Supprimer les items des &eacute;l&eacute;ments du cours';

$string['report'] = 'Voir la progression';
$string['reporttablesummary'] = 'Tableau montrant les &eacute;l&eacute;ments de la liste que chaque &eacute;tudiant a termin&eacute;';

$string['requireditem'] = 'Tableau montrant les &eacute;l&eacute;ments de la liste que chaque &eacute;tudiant a compl&eacute;t&eacute;';

$string['resetchecklistprogress'] = 'R&eacute;initialiser la progression et les items de l\'utilisateur';

$string['savechecks'] = 'Sauvegarder';

$string['showfulldetails'] = 'Afficher tous les d&eacute;tails';
$string['showprogressbars'] = 'Afficher les barres de progression';
///
$string['teachercomments'] = 'Les enseignants peuvent ajouter des commentaires';

$string['teacheredit'] = 'Mises &agrave; jour par';

$string['teachermarkundecided'] = 'L\'enseignant n\'a pas encore coch&eacute; cet item';
$string['teachermarkyes'] = 'L\'enseignant confirme que cet item est complet';
$string['teachermarkno'] = 'L\'enseignant ne confirme pas que cet item est complet';

$string['teachernoteditcheck'] = 'Seulement l\'&eacute;tudiant';
$string['teacheroverwritecheck'] = 'Seulement l\'enseignant';
$string['teacheralongsidecheck'] = 'Etudiant et Enseignant';

$string['toggledates'] = 'Basculer les dates';

$string['theme'] = 'Th&egrave;me graphique pour afficher la Liste des t&acirc;ches';

$string['updatecompletescore'] = 'Sauvegarder les notes d\'ach&egrave;vement';
$string['unindentitem'] = 'Item non indent&eacute;';
$string['updateitem'] = 'Mise &agrave; jour';
$string['useritemsallowed'] = 'L\'utilisateur peut ajouter ses propres items';
$string['useritemsdeleted'] = 'Items de l\'utilisateur supprim&eacute;s';

$string['view'] = 'Voir la Liste des t&acirc;ches';
$string['viewall'] = 'Voir tous les &eacute;tudiants';
$string['viewallcancel'] = 'Effacer';
$string['viewallsave'] = 'Sauvegarder';

$string['viewsinglereport'] = 'Voir la progression de cet utilisateur';
$string['viewsingleupdate'] = 'Mettre &agrave; jour la progression de cet utilisateur';

$string['yesnooverride'] = 'Oui ne peut pas remplacer';
$string['yesoverride'] = 'Oui, peut remplacer';

// CheckListPlus
$string['a_completer'] = 'A COMPLETER';
$string['add_link'] = 'Ajouter un lien ou un document';
$string['addreferentielname'] = 'Saisir un code de référentiel ';
$string['argumentation'] = 'Argumentation';
$string['checklist_check'] = 'Evaluation ';
$string['checklist_description'] = 'Autoriser le dépôt de fichiers';
$string['clicktopreview'] = 'cliquez pour un aperçu pleine taille dans un fenêtre surgissante';
$string['clicktoselect'] = 'cliquez pour sélectionner la vue';
$string['commentby'] = 'Commenté par ';
$string['config_description'] = 'Permet aux utilisateurs de déposer des documents comme trace de pratique.';
$string['config_outcomes_input'] = 'Permet d\'importer dans Checklist les objectifs validés dans les activités Moodle du cours';
$string['confirmreferentielname'] = 'Confirmer le code de référentiel ';
$string['delete_description'] = 'Supprimer la description';
$string['delete_document'] = 'Supprimer un document';
$string['delete_link'] = 'Supprimer un lien';
$string['description_document'] = 'Information sur le document';
$string['description'] = 'Rédigez votre argumentaire';
$string['descriptionh_help'] = 'Indiquez de façon succincte les motifs qui vous permettent d\'affirmer que cette tâche est achevée ou la compétence acquise.';
$string['descriptionh'] = 'Aide pour l\'argumentation';
$string['doc_num'] = 'Document N°{$a} ';
$string['document_associe'] = 'Document associé';
$string['documenth'] = 'Aide pour les documents associés';
$string['documenth_help'] = 'Les documents attachés à une description sont destinés à fournir
des traces observables de votre pratique.

A chaque Item vous pouvez associer une description et un ou plusieurs documents, soit en recopiant son adresse Web (URL),
soit en déposant un fichier dans l\'espace Moodle du cours.

* Description du document : Une courte notice d\'information.

* URL : Adresse Web du document (ou fichier déposé par vos soins dans l\'espace Moodle).

* Titre ou étiquette

* Fenêtre cible où s\'ouvrira le document';
$string['edit_description'] = 'Editer la description';
$string['edit_document'] = 'Editer le document';
$string['edit_link'] = 'Editer un lien';
$string['error_action'] = 'Erreur : Action invalide - "{a}"';
$string['error_checklist_id'] = 'Checklist ID incorrect';
$string['error_cm'] = 'Course Module incorrect';
$string['error_cmid'] = 'Course Module ID incorrect';
$string['error_course'] = 'Course ID incorrect';
$string['error_export_items'] = 'Vous n\'êtes pas autorisé à exporter des items dans cette CheckList';
$string['error_file_upload'] = 'Erreur au chargement du fichierd';
$string['error_import_items'] = 'Vous n\'êtes pas autorisé à importer des items dans cette CheckList';
$string['error_insert_db'] = 'Insertion d\'un item impossible dans la base de données';
$string['error_itemlist'] = 'Erreur : liste d\'items invalide ou absente';
$string['error_number_columns_outcomes'] = 'Cette ligne d\'Objectifs a un nombre incorrect de colonnes :<br />{$a}';
$string['error_number_columns'] = 'Nombre de colonnes incorrect pour cette ligne : <br />{$a}';
$string['error_select'] = 'Erreur: Veuillez sélectionner au moins un Item';
$string['error_sesskey'] = 'Erreur : Clé de session invalide';
$string['error_specif_id'] = 'Vous devez spécifier un course_module ID ou un instance ID';
$string['error_update'] = 'Erreur: Vous n\'êtes pas autorisé à mettre à jour cette CheckList';
$string['error_user'] = 'Compte utilisateur inexistant !';
$string['export_outcomes'] = 'Exporter des objectifs';
$string['id'] = 'ID# ';
$string['import_outcomes'] = 'Importer des objectifs';
$string['input_description'] = 'Rédigez votre argumentaire';
$string['items_exporth_help'] = 'Les items sélectionnés seront exportés dans le même fichier d\'Objectifs.';
$string['items_exporth'] = 'Item exportés';
$string['mustprovideexportformat'] = 'Vous devez fournir un formaat d\'export';
$string['mustprovideinstanceid'] = 'Vous devez fournir un identifiant d\'instance';
$string['mustprovideuser'] = 'Vous devez fournir un identifiant d\'utilisateur';
$string['nomaharahostsfound'] = 'Aucun hôte Mahara n\'a été trouvé.';
$string['noviewscreated'] = 'Vous n\'avez créé aucune vue dans {$a}.';
$string['noviewsfound'] = 'Aucune vue ne correspond dans {$a}';
$string['OK'] = 'OK';
$string['old_comment'] = 'Commentaire antérieur:';
$string['outcome_description'] = 'Description';
$string['outcome_link'] = ' <a href="{$a->link}">{$a->name}</a> ';
$string['outcome_name'] = 'Nom d\'objectif';
$string['outcome_shortname'] = 'Code de compétence';
$string['outcomes_input'] = 'Activer les fichiers d\'objectifs';
$string['outcomes'] = 'outcomes'; // NE PAS TRADUIRE
$string['previewmahara'] = 'Aperçu';
$string['quit'] = 'Quitter';
$string['referentiel_codeh_help'] = 'Le code de référentiel (une chaîne de caractères non accentués sans virgule ni sans espace) permet d\'identifier les compétences (outcomes) participant du même référentiel de compétences. <br />Quand les intitulés d\'Items ne sont pas discriminants cocher <i>"Utiliser l\'ID de l\'Item comme clé"</i>';
$string['referentiel_codeh'] = 'Aide pour la saisie d\'un code de référentiel';
$string['scale_description'] = 'Ce barème est destiné à évaluer l\'acquisition d\'objectifs de compétences.';
$string['scale_items'] = 'Non pertinent,Non validé,Validé';
$string['scale_name'] = 'Item référentiel';
$string['select_all'] = 'Tout cocher';
$string['select_items_export'] = 'Sélectionnez des items à exporter';
$string['select_not_any'] = 'Tous décocher';
$string['select'] = 'Sélectionner';
$string['selectedview'] = 'Page soumissionnée';
$string['selectexport'] = 'Exporter Objectifs';
$string['selectmaharaview'] = 'Sélectionnez dans cette liste l\'une des vues de votre portfolio <i>{$a->name}</i> ou <a href="{$a->jumpurl}">cliquez ici</a> pour créer directement une nouvelle vue sur <i>{$a->name}</i>.';
$string['site_help'] = 'Ce paramètre vous permet de sélectionner depuis quel site Mahara vos étudiants pourront soumettre leurs pages. (Ce site Mahara doit être déjà configuré pour fonctionner en réseau MNET avec ce site Moodle.)';
$string['site'] = 'Site';
$string['target'] = 'Ouvrir ce lien dans une nouvelle fenêtre';
$string['teachermark'] = 'Appréciation ';
$string['teachertimestamp'] = 'Evalué le ';
$string['timecreated'] = 'Créé le ';
$string['timemodified'] = 'Modifié le ';
$string['title'] = 'Titre du document';
$string['titlemahara'] = 'Titre';
$string['typemahara'] = 'Portfolio Mahara';
$string['upload_portfolio'] = 'Lier à une page de mon portfolio';
$string['url'] = 'URL';
$string['urlh_help'] = 'Vous pouvez copier / coller un lien <br />(commençant par "http://"" ou par "https://"") directement dans le champ URL ou bien vous pouvez télécharger un fichier depuis votre poste de travail';
$string['urlh'] = 'Sélection d\'un lien Web';
$string['useitemid'] = 'Utiliser l\'ID de l\'Item comme clé ';
$string['usertimestamp'] = 'Réclamé le ';
$string['viewmahara'] = 'Vue Mahara';
$string['views'] = 'Vues ';
$string['viewsby'] = 'Vues proposées par {$a}';
