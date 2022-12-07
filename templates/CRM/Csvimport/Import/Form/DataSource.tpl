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

<div class="crm-block crm-form-block crm-api-import-uploadfile-form-block">
  {* WizardHeader.tpl provides visual display of steps thru the wizard as well as title for current step *}
  {include file="CRM/common/WizardHeader.tpl"}

  <div id="help">
    {ts}The API Import Wizard allows you to easily upload data against any API create method from other applications into CiviCRM.{/ts}
    {ts}Files to be imported must be in the 'comma-separated-values' format (CSV) and must contain data needed to match the data to an existing record in your CiviCRM database.{/ts} {help id='upload'}
  </div>
  <div id="upload-file" class="form-item">
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>
    <div id="choose-data-source" class="form-item">
      <h3>{ts}Choose Data Source{/ts}</h3>
      <table class="form-layout">
        <tr class="crm-import-datasource-form-block-dataSource">
          <td class="label">{$form.dataSource.label}</td>
          <td>{$form.dataSource.html} {help id='data-source-selection'}</td>
        </tr>
      </table>
    </div>

      {* Data source form pane is injected here when the data source is selected. *}
    <div id="data-source-form-block">
    </div>

    <table class="form-layout">
      <tr class="crm-api-import-entity-form-block-entity">
        <td class="label">{$form.entity.label}</td>
        <td>{$form.entity.html}</td>
      </tr>
      <tr class="crm-api-import-noteEntity-form-block-entity" id="noteEntityWrapper">
        <td class="label">{$form.noteEntity.label}</td>
        <td>{$form.noteEntity.html}<br/>
          <span class="description">
            {ts}Choose 'Set this in CSV' to use 'entity_table' field in next step. This allows you to import 'Notes' to multiple entities in same import.{/ts}
            <br/>
            {ts}Selecting an entity here will allow unique fields of this entity to be used in place of 'id'. Eg: External id for 'Contact'. (note: this will hide 'entity_table' field in next step){/ts}
          </span>
        </td>
      </tr>
      {if array_key_exists('onDuplicate', $form)}
        <tr class="crm-api-import-uploadfile-form-block-onDuplicate">
          <td class="label">{$form.onDuplicate.label}</td>
          <td>{$form.onDuplicate.html}</td>
        </tr>
      {/if}
      <tr class="crm-import-datasource-form-block-fieldSeparator">
        <td class="label">{$form.fieldSeparator.label}</td>
        <td>{$form.fieldSeparator.html} {help id='id-fieldSeparator'}</td>
      </tr>
      <tr class="crm-import-datasource-form-block-allowEntityUpdate">
        <td class="label">{$form.allowEntityUpdate.label}</td>
        <td>{$form.allowEntityUpdate.html} <br/>
          <span class="description">
            {ts}Allow updating an existing entity using unique fields to match (Eg. external_id). By default updating is possible if 'id' is used.{/ts}
          </span>
        </td>
      </tr>
      <tr class="crm-import-datasource-form-block-ignoreCase">
        <td class="label">{$form.ignoreCase.label}</td>
        <td>{$form.ignoreCase.html} <br/>
          <span class="description">
            {ts}Ignore letter-case when mapping values to option fields (Eg. Individual Prefix). Note that this may produce unexpected results if you have multiple option names with same name like Ms. and ms.{/ts}
          </span>
        </td>
      </tr>
      <tr class="crm-api-import-uploadfile-form-block-date_format">
        {include file="CRM/Core/Date.tpl"}
      </tr>
      {if $savedMapping}
      <tr class="crm-api-import-uploadfile-form-block-savedMapping">
        <td class="label">{if $loadedMapping}{ts}Select a Different Field Mapping{/ts}{else}{ts}Load Saved Field
            Mapping{/ts}{/if}
        </td>
        <td><span>{$form.savedMapping.html}</span></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td class="description">{ts}Select Saved Mapping, or leave blank to create a new mapping.{/ts}</td>
        {/if}
      </tr>
    </table>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
  </div>
</div>
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

{literal}
<script type="text/javascript">
  CRM.$(function($) {
    //build data source form block
    buildDataSourceFormBlock();
  });

  function buildDataSourceFormBlock(dataSource)
  {
    var dataUrl = {/literal}"{crmURL p='civicrm/import/datasource' h=0 q='snippet=4'|smarty:nodefaults}"{literal};

    if (!dataSource ) {
      var dataSource = cj("#dataSource").val();
    }

    if ( dataSource ) {
      dataUrl = dataUrl + '&dataSource=' + dataSource;
    } else {
      cj("#data-source-form-block").html( '' );
      return;
    }

    cj("#data-source-form-block").load( dataUrl );
  }
</script>
{/literal}
