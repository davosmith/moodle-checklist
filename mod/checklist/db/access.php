<?php
/**
 * Capability definitions for the checklist module
 */

$mod_checklist_capabilities = array(
      // Ability to view and update own checklist
      'mod/checklist:updateown' => array(
          'riskbitmask' => RISK_SPAM,
          'captype' => 'write',
          'contextlevel' => CONTEXT_MODULE,
          'legacy' => array(
              'student' => CAP_ALLOW
          )
      ),

      // Ability to alter the marks on another person's checklist
      'mod/checklist:updateother' => array(
          'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
          'captype' => 'write',
          'contextlevel' => CONTEXT_MODULE,
          'legacy' => array(
              'teacher' => CAP_ALLOW,
              'editingteacher' => CAP_ALLOW,
              'admin' => CAP_ALLOW
          )
      ),

      // Ability to preview a checklist (to check it is OK)
      'mod/checklist:preview' => array(
          'captype' => 'read',
          'contextlevel' => CONTEXT_MODULE,
          'legacy' => array(
              'teacher' => CAP_ALLOW,
              'editingteacher' => CAP_ALLOW,
              'admin' => CAP_ALLOW
          )
      ),

      // Ability to check up on the progress of all users through
      // their checklists
      'mod/checklist:viewreports' => array(
          'riskbitmask' => RISK_PERSONAL,
          'captype' => 'read',
          'contextlevel' => CONTEXT_MODULE,
          'legacy' => array(
              'teacher' => CAP_ALLOW,
              'editingteacher' => CAP_ALLOW,
              'admin' => CAP_ALLOW
          )
      ),


      // Ability to create and manage checklists
      'mod/checklist:edit' => array(
          'riskbitmask' => RISK_SPAM,
          'captype' => 'write',
          'contextlevel' => CONTEXT_MODULE,
          'legacy' => array(
              'editingteacher' => CAP_ALLOW,
              'admin' => CAP_ALLOW
          )
      ),

      // Will receive emails when checklists complete (if checklist is set to do so)
      'mod/checklist:emailoncomplete' => array(
           'riskbitmask' => RISK_PERSONAL,
           'captype' => 'read',
           'contextlevel' => CONTEXT_MODULE,
           'legacy' => array(
              'editingteacher' => CAP_ALLOW,
              'teacher' => CAP_ALLOW
           )
      ),

      // Can update teacher checklist marks even if locked
      'mod/checklist:updatelocked' => array(
           'captype' => 'write',
           'contextlevel' => CONTEXT_MODULE,
           'legacy' => array(
              'editingteacher' => CAP_ALLOW,
              'admin' => CAP_ALLOW
           )
      )
);
