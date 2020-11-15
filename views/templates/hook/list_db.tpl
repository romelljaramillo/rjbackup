{*
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 *  @author Roanja <info@roanja.com>
 *  @copyright  2019 Roanja
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of Roanja
*}
<div class="panel col-lg-12">
	<h3>
		<i class="icon-database"></i> 
		{l s='backups list data base' mod='rjbackup'}
		<span class="panel-heading-action">
			<a id="desc-product-new" class="list-toolbar-btn" href="{$link->getAdminLink('AdminModules')|escape:'htmlall':'UTF-8'}&configure=rjbackup&create_Backup=db">
				<span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="{l s='Add new' mod='rjbackup'}" data-html="true">
					<i class="process-icon-new "></i>
				</span>
			</a>
		</span>
	</h3>
	<div id="backupsContentDB" class="table-responsive-row clearfix">
		<table id="backupDB" class="table backupDB">
			<thead>
				<tr class="nodrag nodrop">
					<th class="">
						<span class="title_box">
							{l s='backup' mod='rjbackup'}
						</span>
					</th>
					<th class="">
						<span class="title_box">
							{l s='Size' mod='rjbackup'}
						</span>
					</th>
					<th class="">
						<span class="title_box">
							{l s='Date' mod='rjbackup'}
						</span>
					</th>
					<th class="">
					</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$backups item=backup}
				<tr class="add">
					<td><a href="{$dir|escape:'htmlall':'UTF-8'}{$backup.file|escape:'htmlall':'UTF-8'}" alt="{$backup.file|escape:'htmlall':'UTF-8'}" class="">{$backup.file|escape:'htmlall':'UTF-8'}</a></td>
					<td>{$backup.size|escape:'htmlall':'UTF-8'}</td>
					<td>{$backup.date|escape:'htmlall':'UTF-8'}</td>
					<td>
						<div class="btn-group-action pull-right">
							<a class="btn btn-default"
								title= "{l s='Download' mod='rjbackup'}"
								href="{$dir|escape:'htmlall':'UTF-8'}{$backup.file|escape:'htmlall':'UTF-8'}">
								<i class="icon-download"></i>
							</a>
							<a class="btn btn-default"
								title= "{l s='Delete' mod='rjbackup'}"
								href="{$link->getAdminLink('AdminModules')|escape:'htmlall':'UTF-8'}&configure=rjbackup&type_file=db&delete_id_backup={$backup.file|escape:'htmlall':'UTF-8'}">
								<i class="icon-trash"></i>
							</a>
							<a class="btn btn-default"
								title= "{l s='send for FTP' mod='rjbackup'}"
								href="{$link->getAdminLink('AdminModules')|escape:'htmlall':'UTF-8'}&configure=rjbackup&type_file=db&send_ftp={$backup.file|escape:'htmlall':'UTF-8'}">
								<i class="icon-send"></i>
							</a>
						</div>
					</td>
				</tr>
				{/foreach}
			</tbody>
		</table>
	</div>
</div>
