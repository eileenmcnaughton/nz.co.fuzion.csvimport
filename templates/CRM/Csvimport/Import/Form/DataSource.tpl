{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
{* API Import Wizard - Step 1 (upload data file) *}
{* @var $form Contains the array for the form elements and other form associated information assigned to the template by the controller *}
{include file="CRM/Import/Form/DataSource.tpl"}
<table class="form-layout">
  <tr class="crm-api-import-entity-form-block-entity">
    <td class="label">{$form.entity.label}</td>
    <td>{$form.entity.html}</td>
  </tr>
  <tr class="crm-api-import-noteEntity-form-block-entity" id="noteEntityWrapper">
    <td class="label">{$form.noteEntity.label}</td>
    <td>{$form.noteEntity.html}<br/>
  </tr>
  <tr class="crm-import-datasource-form-block-allowEntityUpdate">
    <td class="label">{$form.allowEntityUpdate.label}</td>
    <td>{$form.allowEntityUpdate.html} <br/>
      <span class="description">
            {ts}Allow updating an existing entity using unique fields to match (Eg. external_id). By default updating is possible if 'id' is used.{/ts}
            {ts}Checking this gives core-like behaviour{/ts}
          </span>
    </td>
  </tr>
  <tr class="crm-import-datasource-form-block-ignoreCase">
    <td class="label">{$form.ignoreCase.label}</td>
    <td>{$form.ignoreCase.html} <br/>
      <span class="description">
            {ts}Ignore letter-case when mapping values to option fields (Eg. Individual Prefix). Note that this may produce unexpected results if you have multiple option names with same name like Ms. and ms.{/ts}
            {ts}Note that core imports are case-insensitive so checking this gives 'core-like' behaviour{/ts}
          </span>
    </td>
  </tr>
</table>
{literal}
  <script type="text/javascript">
    CRM.$('select#entity').on('change', function () {
      if (CRM.$(this).val() == 'Note') {
        CRM.$('#noteEntityWrapper').show();
      } else {
        CRM.$('#noteEntityWrapper').hide();
      }
    });
    if (CRM.$('select#entity option:selected').val() == 'Note') {
      CRM.$('#noteEntityWrapper').show();
    } else {
      CRM.$('#noteEntityWrapper').hide();
    }
  </script>
{/literal}
