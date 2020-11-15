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
<div class="panel">
	<h3><i class="icon-list-ul"></i> 
		{l s='Create backup' mod='rjbackup'}
	</h3>
	<div id="BackupContent">
		<a class="btn btn-success btn-lg btn-block"
			href="{$link->getAdminLink('AdminModules')|escape:'htmlall':'UTF-8'}&configure=rjbackup&test_ftp=test">
			<i class="icon-link"></i>  {l s='Test' mod='rjbackup'}
			{l s='connect FTP.' mod='rjbackup'}
		</a>
		<a class="btn btn-info btn-lg btn-block"
			href="{$link->getAdminLink('AdminModules')|escape:'htmlall':'UTF-8'}&configure=rjbackup&create_Backup=db">
			<i class="icon-plus-circle"></i>  {l s='Create' mod='rjbackup'}
			{l s='I have read the disclaimer. Please create a new backup data base.' mod='rjbackup'}
		</a>
		<a class="btn btn-info btn-lg btn-block"
			href="{$link->getAdminLink('AdminModules')|escape:'htmlall':'UTF-8'}&configure=rjbackup&create_Backup=f">
			<i class="icon-plus-circle"></i>  {l s='Create' mod='rjbackup'}
			{l s='I have read the disclaimer. Please create a new backup files.' mod='rjbackup'}
		</a>
    </div>
</div>