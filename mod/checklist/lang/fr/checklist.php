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

// Modifié par JF - jean.fruitet@univ-nantes.fr 2012/03/18
// MODIF JF 2012/03/18 //////////////////////////////////////////////////////////////

$string['outcomes_input'] = 'Activer les fichiers d\'objectifs';
$string['config_outcomes_input'] = 'Permet d\'importer dans Checklist les objectifs validés dans les activités Moodle du cours';
$string['checklist_description'] = 'Autoriser le dépôt de fichiers';
$string['config_description'] = 'Permet aux utilisateurs de déposer des documents comme trace de pratique.';

// error strings
$string['error_cmid'] = 'Course Module ID incorrect';
$string['error_cm'] = 'Course Module incorrect';
$string['error_course'] = 'Course ID incorrect';
$string['error_specif_id'] = 'Vous devez spécifier un course_module ID ou un instance ID';

$string['error_checklist_id'] = 'Checklist ID incorrect';
$string['error_user'] = 'Compte utilisateur inexistant !';
$string['error_sesskey'] = 'Erreur : Clé de session invalide';
$string['error_action'] = 'Erreur : Action invalide - "{a}"';
$string['error_itemlist'] = 'Erreur : liste d\'items invalide ou absente';

$string['error_import_items'] = 'Vous n\'êtes pas autorisé à importer des items dans cette CheckList';
$string['error_export_items'] = 'Vous n\'êtes pas autorisé à exporter des items dans cette CheckList';
$string['error_file_upload'] = 'Erreur au chargement du fichierd';
$string['error_number_columns'] = 'Nombre de colonnes incorrect pour cette ligne : <br />{$a}';
$string['error_insert_db'] = 'Insertion d\'un item impossible dans la base de données';
$string['error_update'] = 'Erreur: Vous n\'êtes pas autorisé à mettre à jour cette CheckList';
$string['error_select'] = 'Erreur: Veuillez sélectionner au moins un Item';
$string['OK'] = 'OK';
$string['quit'] = 'Quitter';

// Outcomes
$string['useitemid'] = 'Utiliser l\'ID de l\'Item comme clé ';
$string['a_completer'] = 'A COMPLETER';
$string['selectexport'] = 'Exporter Objectifs';
$string['addreferentielname'] = 'Saisir un code de référentiel ';
$string['confirmreferentielname'] = 'Confirmer le code de référentiel ';
$string['referentiel_codeh'] = 'Aide pour la saisie d\'un code de référentiel';
$string['referentiel_codeh_help'] = 'Le code de référentiel (une chaîne de caractères non accentués sans virgule ni sans espace) permet d\'identifier les compétences (outcomes) participant du même référentiel de compétences.
<br />Quand les intitulés d\'Items ne sont pas discriminants cocher <i>"'.$string['useitemid'].'</i>"';
$string['select_items_export'] = 'Sélectionnez des items à exporter';
$string['items_exporth'] = 'Item exportés';
$string['items_exporth_help'] = 'Les items sélectionnés seront exportés dans le même fichier d\'Objectifs.';
$string['select_all'] = 'Tout cocher';
$string['select_not_any'] = 'Tous décocher';

$string['export_outcomes'] = 'Exporter des objectifs';
$string['import_outcomes'] = 'Importer des objectifs';
$string['error_number_columns_outcomes'] = 'Cette ligne d\'Objectifs a un nombre incorrect de colonnes :<br />{$a}';
$string['old_comment'] = 'Commentaire antérieur:';
$string['outcomes'] = 'outcomes'; // NE PAS TRADUIRE
$string['outcome_link'] = ' <a href="{$a->link}">{$a->name}</a> ';
$string['outcome_name'] = 'Nom d\'objectif';
$string['outcome_shortname'] = 'Code de compétence';
$string['outcome_description'] = 'Description';
// Scale / Bareme
$string['scale_name'] = 'Item référentiel';
$string['scale_items'] = 'Non pertinent,Non validé,Validé';
$string['scale_description'] = 'Ce barème est destiné à évaluer l\'acquisition d\'objectifs de compétences.';

// Description
$string['edit_description'] = 'Editer une description';
$string['input_description'] = 'Rédigez votre argumentaire';
$string['descriptionh'] = 'Aide pour l\'argumentation';
$string['descriptionh_help'] = 'Indiquez de façon succincte les motifs qui vous permettent
d\'affirmer que cette tâche est achevée ou la compétence acquise.';
$string['description'] = 'Rédigez votre argumentaire';
$string['delete_description'] = 'Supprimer une description';

// Document
$string['urlh'] = 'Sélection d\'un lien Web';
$string['urlh_help'] = 'Vous pouvez copier / coller un lien <br />(commençant par "http://"" ou par "https://"")
directement dans le champ URL ou bien vous pouvez télécharger un fichier depuis votre poste de travail';
$string['add_link'] = 'Ajouter un lien';
$string['delete_link'] = 'Supprimer un lien';
$string['edit_link'] = 'Editer un lien';
$string['doc_num'] = 'Document N°{$a} ';
$string['edit_document'] = 'Editer un document';
$string['document_associe'] = 'Document associé';
$string['url'] = 'URL';
$string['description_document'] = 'Information sur le document';
$string['target'] = 'Ouvrir ce lien dans une nouvelle fenêtre';
$string['title'] = 'Titre du document';
$string['delete_document'] = 'Supprimer un document';

$string['documenth'] = 'Aide pour les documents associés';
$string['documenth_help'] = 'Les documents attachés à une description sont destinés à fournir
des traces observables de votre pratique.
<br />
A chaque Item vous pouvez associer une description et un ou plusieurs documents, soit en recopiant son adresse Web (URL),
soit en déposant un fichier dans l\'espace Moodle du cours.
<br />Description du document : Une courte notice d\'information.
<br />URL : Adresse Web du document
<br /> &nbsp; &nbsp; (ou fichier déposé par vos soins dans l\'espace Moodle).
<br />Titre ou étiquette
<br />Fenêtre cible où s\'ouvrira le document';



/////////////////////////////////////////////////////////////////////////////////////

$string['addcomments'] = 'Ajouter des commentaires';

$string['additem'] = 'Ajouter';
$string['additemalt'] = 'Ajouter un nouvel item à la liste';
$string['additemhere'] = 'Insérer le nouvel item après celui-ci';
$string['addownitems'] = 'Ajouter vos propres items';
$string['addownitems-stop'] = 'Arrêt d\'ajout d\'items';

$string['allowmodulelinks'] = 'Autoriser les liens vers les éléments';

$string['anygrade'] = 'Tout';
$string['autopopulate'] = 'Montrer les éléments du cours dans la CheckList';
$string['autopopulate_help'] = 'Cela ajoutera automatiquement une liste de toutes les ressources et les activités dans le cadre actuel dans la liste. <br />
Cette liste sera mise à jour avec tous les changements en cours, lorsque vous visitez la page "Modifier" pour la CheckList. <br />
Les items peuvent être cachés dans la liste, en cliquant sur l\'icône \Cacher" à côté d\'eux.<br />
Pour supprimer les items automatiques de la liste, modifier cette option en cliquant sur "Non", puis cliquez sur "Supprimer des éléments de cours" sur la page "Modifier".';
$string['autoupdate'] = 'Cochez quand les modules sont complets';
$string['autoupdate_help'] = 'Cela va automatiquement cocher les éléments de votre CheckList lorsque vous terminez l\'activité concernée dans le cours. <br />
"Finir" une activité varie d\'une activité à l\'autre - "voir" une ressource, "envoyer" un quiz ou un fichier, "répondre" à un forum ou participez à un chat, etc <br />
Si un suivi de fin de Moodle 2.0 est activé pour une activité particulière, il sera utilisé pour les cocher l\'élément dans la liste <br />
Pour plus de détails sur la cause exacte qu\'une activité peut être marqué comme "achevée", demandez à votre administrateur du site pour regarder dans le fichier "mod / liste / autoupdate.php" <br />
Remarque: cel peut prendre jusqu\'à 60 secondes pour que l\'activité d\'un étudiant se mette à jour dans leur CheckList';

$string['autoupdatewarning_both'] = 'Il ya des items sur cette liste qui seront automatiquement mis à jour (comme ceux que les étudiants disent "complet"). Cependant, dans le cas d\'une CheckList commune "étudiant et enseignant", les barres de progression ne seront pas mise à jour tant qu\'un enseignant accepte les notes attribuées.';
$string['autoupdatewarning_student'] = 'Il ya des items sur cette liste qui seront automatiquement mis à jour (comme ceux que les étudiants disent "complet").';
$string['autoupdatewarning_teacher'] = 'La mise à jour automatique a été activée pour cette liste, mais ces remarques ne seront pas affichée tant que l\'enseignant ne les montrent pas.';

$string['canceledititem'] = 'Effacer';

$string['calendardescription'] = 'Cet élément a été ajouté par la CheckList : {$a}';

$string['changetextcolour'] = 'Prochaine couleur de texte';

$string['checkeditemsdeleted'] = 'Items de la CheckList supprimés';

$string['checklist'] = 'CheckList';
$string['pluginadministration'] = 'Administration de la CheckList';

$string['checklist:edit'] = 'Créer et éditer des CheckList';
$string['checklist:emailoncomplete'] = 'Recevoir par mail quand c\'est complet';
$string['checklist:preview'] = 'Prévisualisation d\'une CheckList';
$string['checklist:updatelocked'] = 'Mise à jour des marques verrouillée';
$string['checklist:updateother'] = 'Mise à jour des marques des CheckList des étudiants';
$string['checklist:updateown'] = 'Mise à jour de vos marques des CheckList';
$string['checklist:viewreports'] = 'Voir la progression des étudiants';

$string['checklistautoupdate'] = 'Autoriser les CheckList à se mettre à jour automatiquement';

$string['checklistfor'] = 'CheckList pour';

$string['checklistintro'] = 'Introduction';
$string['checklistsettings'] = 'Paramètres';

$string['checks'] = 'Marques';
$string['comments'] = 'Commentaires';

$string['completionpercentgroup'] = 'A cocher obligatoirement';
$string['completionpercent'] = 'Pourcentage d\'items qui doivent être cochés :';

$string['configchecklistautoupdate'] = 'Avant de permettre cela, vous devez faire quelques modifications au code Moodle, merci de voir le "mod / liste / README.txt" pour plus de détails';

$string['confirmdeleteitem'] = 'Etes-vous sûr de vouloir effacer définitivement cet item de la CheckList ?';

$string['deleteitem'] = 'Effacer cet item';

$string['duedatesoncalendar'] = 'Ajouter les dates d\'échéance au calendrier';

$string['edit'] = 'Editer la CheckList';
$string['editchecks'] = 'Editer les coches';
$string['editdatesstart'] = 'Editer les dates';
$string['editdatesstop'] = 'Arrêt de l\édition des dates';
$string['edititem'] = 'Editer cet item';

$string['emailoncomplete'] = 'Envoyer un courriel à l\'enseignant quand la CheckList est complète';
$string['emailoncomplete_help'] = 'Quand une liste est complète, un courriel de notification est envoyé à tous les enseignants du cours. <br />
Un administrateur peut contrôler qui reçoit ce courriel en utilisant la capacité "mod: check-list / emailoncomplete" - par défaut, tous les enseignants et non enseignants non éditeur ont cette capacité.';
$string['emailoncompletesubject'] = 'L\utilisateur {$a->user} a complété sa Liste de items \'{$a->checklist}\'';
$string['emailoncompletebody'] = 'L\utilisateur {$a->user} a complété sa Liste de items \'{$a->checklist}\'
Voir la CheckList ici :';

$string['export'] = 'Exporter des items';

$string['forceupdate'] = 'Mise à jour des coches pour les items automatiques';

$string['gradetocomplete'] = 'Evaluation pour terminer';
$string['guestsno'] = 'Vous n\'avez pas la permission de voir cette CheckList';

$string['headingitem'] = 'Cet item est une étiquette, il n\'y aura pas de case à cocher à côté';

$string['import'] = 'Importer des items';
$string['importfile'] = 'Choisir le fichier à importer';
$string['importfromsection'] = 'Section courante';
$string['importfromcourse'] = 'Tout le cours';
$string['indentitem'] = 'Décaller l\'item';
$string['itemcomplete'] = 'Terminé';
$string['items'] = 'Items de la CheckList';

$string['linktomodule'] = 'Lien de la ressource ou de l\'activité';

$string['lockteachermarks'] = 'Verrouillage des coches de l\'enseignant';
$string['lockteachermarks_help'] = 'Lorsque ce paramètre est activé, une fois qu\'un enseignant a sauvé une coche "Oui", il ne sera plus possible de changer la valeur. Les utilisateurs ayant la capacité "mod / check-list: updatelocked" sera toujours en mesure de changer la coche.';
$string['lockteachermarkswarning'] = 'Remarque: Une fois que vous avez enregistré ces coches, il vous sera impossible de changer toutes les coches "Oui"';

$string['modulename'] = 'CheckList';
$string['modulenameplural'] = 'CheckLists';

$string['moveitemdown'] = 'Descendre l\'item';
$string['moveitemup'] = 'Monter l\'item';

$string['noitems'] = 'Pas d\'items dans la CheckList';

$string['optionalitem'] = 'Cet item est optionnel';
$string['optionalhide'] = 'Cacher les options des items';
$string['optionalshow'] = 'Montrer les options des items';

$string['percentcomplete'] = 'Items obligatoires';
$string['percentcompleteall'] = 'Tous les items';
$string['pluginname'] = 'CheckList';
$string['preview'] = 'Prévisualisation';
$string['progress'] = 'Progression';

$string['removeauto'] = 'Supprimer les items des éléments du cours';

$string['report'] = 'Voir la progression';
$string['reporttablesummary'] = 'Table affichant les éléments de la CheckList que chaque étudiant a complétées';

$string['requireditem'] = 'Tableau montrant les éléments de la liste que chaque étudiant a complété';

$string['resetchecklistprogress'] = 'Réinitialiser la progression et les items de l\'utilisateur';

$string['savechecks'] = 'Sauvegarder';

$string['showfulldetails'] = 'Afficher tous les détailsShow full details';
$string['showprogressbars'] = 'Afficher les barres de progression';
///
$string['teachercomments'] = 'Les enseignants peuvent ajouter des commentaires';

$string['teacheredit'] = 'Mises à jour par';

$string['teachermarkundecided'] = 'L\'enseignant n\'a pas encore coché cet item';
$string['teachermarkyes'] = 'L\enseignant confirme que cet item est complet';
$string['teachermarkno'] = 'L\enseignant ne confirme pas que cet item est complet';

$string['teachernoteditcheck'] = 'Seulement l\'étudiant';
$string['teacheroverwritecheck'] = 'Seulement l\'enseignant';
$string['teacheralongsidecheck'] = 'Etudiant et Enseignant';

$string['toggledates'] = 'Basculer les dates';

$string['theme'] = 'Thème graphique pour afficher la CheckList';

$string['updatecompletescore'] = 'Sauvegarder les notes d\'achèvement';
$string['unindentitem'] = 'Item non indenté';
$string['updateitem'] = 'Mise à jour';
$string['useritemsallowed'] = 'L\'utilisateur peut ajouter ses propres items';
$string['useritemsdeleted'] = 'Items de l\'utilisateur supprimés';

$string['view'] = 'Voir la CheckList';
$string['viewall'] = 'Voir tous les étudiants';
$string['viewallcancel'] = 'Effacer';
$string['viewallsave'] = 'Sauvegarder';

$string['viewsinglereport'] = 'Voir la progression de cet utilisateur';
$string['viewsingleupdate'] = 'Mettre à jour la progression de cet utilisateur';

$string['yesnooverride'] = 'Oui ne peut pas remplacer';
$string['yesoverride'] = 'Oui, peut remplacer';
