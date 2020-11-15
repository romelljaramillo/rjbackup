<?php
/**
 * 2019 Roanja
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@roanja.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Roanja to newer
 * versions in the future. If you wish to customize Roanja for your
 * needs please refer to http://www.roanja.com for more information.
 *
 *  @author Roanja <info@roanja.com>
 *  @copyright  2019 Roanja
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of Roanja
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once _PS_MODULE_DIR_ . 'rjbackup/ftp/FtpClass.php';

class RjBackup extends Module
{
    public $id;
    protected $html = '';
    private $errors = array();
    private $ftp_pasv = true;
    private $dir_backup_db = 'db_backup';
    private $dir_backup_file = 'file_backup';
    private $config;
    private $rjBackupAll = true;
    private $rjBackupDropTable = true;
    private $dir_backup_uri = __PS_BASE_URI__;
    private $dir_backup_in = _PS_ROOT_DIR_;
    private $dir_module_re = _MODULE_DIR_;
    private $dir_module_ab = _PS_MODULE_DIR_;
    private $ignore_dir = array(".", "..", "backups", "var", "rjbackup");
    private $dirsIgnoreActives = array();

    public function __construct()
    {
        $this->name = 'rjbackup';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Roanja';
        $this->module_key = 'b11f0b5c0883b1e620823e4a0d0f90ee';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Roanja Backup');
        $this->description = $this->l('Module to create backup copies of your prestashop store, database and files, send by FTP.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        $this->rjBackupAll = Configuration::get('PS_BACKUP_ALL');
        $this->rjBackupDropTable = Configuration::get('PS_BACKUP_DROP_TABLE');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function install()
    {
        if (parent::install()) {
            // return (bool)$res;
            $shops = Shop::getContextListShopID();
            $shop_groups_list = array();

            /* Setup each shop */
            foreach ($shops as $shop_id) {
                $shop_group_id = (int) Shop::getGroupFromShop($shop_id, true);

                if (!in_array($shop_group_id, $shop_groups_list)) {
                    $shop_groups_list[] = $shop_group_id;
                }

                /* Sets up configuration */
                $res = Configuration::updateValue('ftp_pasv', $this->ftp_pasv, false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('dir_backup_db', $this->dir_backup_db, false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('dir_backup_file', $this->dir_backup_file, false, $shop_group_id, $shop_id);
            }

            /* Sets up Shop Group configuration */
            if (count($shop_groups_list)) {
                foreach ($shop_groups_list as $shop_group_id) {
                    $res &= Configuration::updateValue('ftp_pasv', $this->ftp_pasv, false, $shop_group_id);
                    $res &= Configuration::updateValue('dir_backup_db', $this->dir_backup_db, false, $shop_group_id);
                    $res &= Configuration::updateValue('dir_backup_file', $this->dir_backup_file, false, $shop_group_id);
                }
            }

            /* Sets up Global configuration */
            $res &= Configuration::updateValue('ftp_pasv', $this->ftp_pasv);
            $res &= Configuration::updateValue('dir_backup_db', $this->dir_backup_db);
            $res &= Configuration::updateValue('dir_backup_file', $this->dir_backup_file);

            $res &= $this->createTables();

            return (bool) $res;
        }

        return false;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function uninstall()
    {
        /* Deletes Module */
        if (parent::uninstall()) {
            $res = $this->deleteTables();
            /* Unsets configuration */
            $res &= Configuration::deleteByName('dir_backup_db');
            $res &= Configuration::deleteByName('dir_backup_file');
            $res &= Configuration::deleteByName('ftp_protocol');
            $res &= Configuration::deleteByName('ftp_host');
            $res &= Configuration::deleteByName('ftp_port');
            $res &= Configuration::deleteByName('ftp_user');
            $res &= Configuration::deleteByName('ftp_password');
            $res &= Configuration::deleteByName('ftp_pasv');
            $res &= Configuration::deleteByName('ftp_remote_dir');

            return (bool) $res;
        }

        return false;
    }

    /**
     * deletes tables
     */
    protected function deleteTables()
    {
        return Db::getInstance()->execute('
            DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rjbackup_dir`;
        ');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    protected function createTables()
    {
        $res = Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rjbackup_dir` (
              `file` varchar(255) NOT NULL,
              `date` DATETIME NULL,
              `active` tinyint(1) unsigned NOT NULL DEFAULT \'0\',
              PRIMARY KEY (`file`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;
        ');

        return $res;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getContent()
    {
        if (Tools::isSubmit('submitConfiBackup')
            || Tools::isSubmit('submitConfigFtp')
            || Tools::isSubmit('create_Backup')
            || Tools::isSubmit('delete_id_backup')
            || Tools::isSubmit('send_ftp')
            || Tools::isSubmit('test_ftp')
            || Tools::isSubmit('activedirbackup')
        ) {
            if ($this->postValidation()) {
                $this->postProcess();
            }
        }

        $this->html .= $this->displayWarningLib();
        $this->html .= $this->displayInfoCron();
        $this->html .= '<div class="row"><div class="col-md-6 col-xs-12">';
        $this->html .= $this->renderListDirs();
        $this->html .= $this->renderFormCreateBackup();
        $this->html .= '</div><div class="col-md-6 col-xs-12">';
        $this->html .= $this->renderFormHost();
        $this->html .= $this->renderFormFtp();
        $this->html .= '</div></div>';
        $this->html .= '<div class="row"><div class="col-md-6 col-xs-12">';
        $this->html .= $this->renderListBackupDB();
        $this->html .= '</div><div class="col-md-6 col-xs-12">';
        $this->html .= $this->renderListBackupFiles();
        $this->html .= '</div></div>';

        return $this->html;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    protected function postValidation()
    {
        $errors = array();

        if (Tools::isSubmit('submitConfiBackup') || Tools::isSubmit('create_Backup')) {
            if (!Validate::isDirName(Tools::getValue('dir_backup_db')) && empty(Tools::getValue('dir_backup_db'))) {
                $errors[] = $this->l('the database backup directory is not set.');
            }

            if (!Validate::isDirName(Tools::getValue('dir_backup_file'))) {
                $errors[] = $this->l('the files backup directory is not set.');
            }
        } elseif (Tools::isSubmit('submitConfigFtp')) {
            if (empty(Tools::getValue('ftp_host'))) {
                $errors[] = $this->l('FTP server is not defined.');
            }
            if (empty(Tools::getValue('ftp_user'))) {
                $errors[] = $this->l('FTP user is not defined.');
            }
            if (empty(Tools::getValue('ftp_password'))) {
                $errors[] = $this->l('FTP password is not defined.');
            }
        } elseif (Tools::isSubmit('test_ftp') || Tools::isSubmit('send_ftp')) {
            $dataFTP = $this->getFtpConfigFieldsValues();

            if (!isset($dataFTP['ftp_host']) || empty($dataFTP['ftp_host'])) {
                $errors[] = $this->l('FTP server is not defined.');
            }
            if (!isset($dataFTP['ftp_user']) || empty($dataFTP['ftp_user'])) {
                $errors[] = $this->l('FTP user is not defined.');
            }
            if (!isset($dataFTP['ftp_password']) || empty($dataFTP['ftp_password'])) {
                $errors[] = $this->l('FTP password is not defined.');
            }
        }

        if (count($errors)) {
            $this->html .= $this->displayError(implode('<br />', $errors));

            return false;
        }

        return true;
    }
    /**
     * Undocumented function
     *
     * @return void
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('submitConfiBackup')) {
            $shop_groups_list = array();
            $shops = Shop::getContextListShopID();

            $this->createDirBackup(Tools::getValue('dir_backup_db'));
            $this->createDirBackup(Tools::getValue('dir_backup_file'));

            foreach ($shops as $shop_id) {
                $shop_group_id = (int) Shop::getGroupFromShop($shop_id, true);

                if (!in_array($shop_group_id, $shop_groups_list)) {
                    $shop_groups_list[] = $shop_group_id;
                }

                $res = Configuration::updateValue('dir_backup_db', Tools::getValue('dir_backup_db'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('dir_backup_file', Tools::getValue('dir_backup_file'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('PS_BACKUP_ALL', Tools::getValue('PS_BACKUP_ALL'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('PS_BACKUP_DROP_TABLE', Tools::getValue('PS_BACKUP_DROP_TABLE'), false, $shop_group_id, $shop_id);
            }

            if (!$res) {
                $this->errors[] = $this->l('The configuration could not be updated.');
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=6&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
            }
        } elseif (Tools::isSubmit('submitConfigFtp')) {
            $shop_groups_list = array();
            $shops = Shop::getContextListShopID();

            foreach ($shops as $shop_id) {
                $shop_group_id = (int) Shop::getGroupFromShop($shop_id, true);

                if (!in_array($shop_group_id, $shop_groups_list)) {
                    $shop_groups_list[] = $shop_group_id;
                }

                $res = Configuration::updateValue('ftp_protocol', Tools::getValue('ftp_protocol'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('ftp_host', Tools::getValue('ftp_host'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('ftp_port', Tools::getValue('ftp_port'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('ftp_user', Tools::getValue('ftp_user'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('ftp_password', Tools::getValue('ftp_password'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('ftp_pasv', Tools::getValue('ftp_pasv'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('ftp_remote_dir', Tools::getValue('ftp_remote_dir'), false, $shop_group_id, $shop_id);
            }

            if (!$res) {
                $this->errors[] = $this->l('The configuration FTP.');
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=6&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
            }
        } elseif (Tools::isSubmit('activedirbackup')) {
            $res = $this->activeDir(Tools::getValue('file'));

            if (!$res) {
                $this->errors[] = $this->l('Error activation dir.');
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&conf=4&token=' . Tools::getAdminTokenLite('AdminModules'));
            }
        } elseif (Tools::isSubmit('create_Backup')) {
            if (Tools::strtoupper(Tools::getValue('create_Backup')) == 'F') {
                $res = $this->addBackupFile();
            } else {
                $res = $this->addBackupDB();
            }

            if (!$res) {
                $this->errors[] = $this->l('Error to create backup.');
            } else {
                if (!$this->sendFtp($this->id, Tools::getValue('create_Backup'))) {
                    $this->errors[] = $this->l('Error to send FTP');
                } else {
                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=3&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
                }
            }
        } elseif (Tools::isSubmit('delete_id_backup')) {
            if (Tools::strtoupper(Tools::getValue('type_file')) == 'F') {
                $dir = $this->getDirbackup('', 'F');
            } else {
                $dir = $this->getDirbackup();
            }

            $this->id = $dir . Tools::getValue('delete_id_backup');
            $res = $this->delete();

            if (!$res) {
                $this->errors[] = $this->l('Could not delete.');
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=1&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
            }
        } elseif (Tools::isSubmit('send_ftp')) {
            $this->id = null;
            $res = $this->sendFtp(Tools::getValue('send_ftp'), Tools::getValue('type_file'));

            if (!$res) {
                $this->errors[] = $this->l('Error send FTP');
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=3&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
            }
        } elseif (Tools::isSubmit('test_ftp')) {
            if (!$this->testConnect()) {
                $this->errors[] = $this->l('ERROR test FTP.');
            } else {
                $this->html .= $this->displayConfirmation($this->l('SUCCESS connect FTP'));
            }
        }

        if (count($this->errors)) {
            $this->html .= $this->displayError(implode('<br />', $this->errors));
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function displayInfoCron()
    {
        $rjbackup_cron_url = Tools::getShopDomain(true, true) . $this->dir_backup_uri . basename($this->dir_module_ab);
        $rjbackup_cron_url .= '/' . $this->name . '/cron_rjbackup.php?secure_key=' . md5(_COOKIE_KEY_ . Configuration::get('PS_SHOP_NAME'));

        $mens = $this->l('To run your cron tasks, please insert the following line into your cron task manager.');
        $output = '
        <div class="bootstrap">
        <div class="module_info info alert alert-info">
            <p>' . $mens . '</p>
            <ul class="list-unstyled">
                <li><code>0 * * * * curl "' . $rjbackup_cron_url . '"</code></li>
            </ul>
        </div>
        </div>';

        return $output;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function displayWarningLib()
    {
        $mens_exist_lib = array();

        if (!function_exists("ftp_connect")) {
            $mens_exist_lib[] = $this->l('php ftp_connect library missing.');
        }

        if (!function_exists("ssh2_connect")) {
            $mens_exist_lib[] = $this->l('php ssh2_connect library missing.');
        }

        if (!class_exists("ZipArchive")) {
            $mens_exist_lib[] = $this->l('php ZipArchive library missing.');
        }

        if (count($mens_exist_lib)) {
            $mens_exist_lib[] = $this->l('php libraries that are not available in your web hosting.');

            $output = $this->displayWarning(implode('<br />', $mens_exist_lib));
            return $output;
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getDirs()
    {
        $list_dir = array();
        $root = $this->dir_backup_in;
        $dirs = scandir($root);
        $dirsDB = $this->getDirDB();
        foreach ($dirs as $dir) {
            if (is_dir($root . '/' . $dir) && $dir != '..' && $dir != '.') {
                $item = array(
                    'icon' => 'folder',
                    'file' => $dir,
                    'date' => date("Y-m-d H:i:s", filemtime($root . '/' . $dir)),
                    'active' => $dirsDB[$dir]['active'],
                );
                array_push($list_dir, $item);
            }
        }

        return $list_dir;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function renderListDirs()
    {
        $dirs = $this->getDirs();

        $icon = array('default' => 'folder.gif');
        $fields_list = array(
            'icon' => array(
                'title' => '',
                'icon' => $icon,
                'width' => 10,
                'search' => false,
            ),
            'file' => array(
                'title' => $this->l('Dir'),
                'width' => 50,
                'search' => false,
            ),
            'date' => array(
                'title' => $this->l('Date'),
                'width' => 30,
                'type' => 'datetime',
                'search' => false,
            ),
            'active' => array(
                'title' => $this->l('Dir ignore'),
                'width' => 10,
                'type' => 'bool',
                'active' => 'active',
                'search' => false,
            ),
        );

        if (!Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            unset($fields_list['shop_name']);
        }

        $helper_list = new HelperList();
        $helper_list->module = $this;
        $helper_list->title = $this->l('Dirs backups');
        $helper_list->title_icon = 'icon-folder';
        $helper_list->shopLinkType = '';
        $helper_list->no_link = true;
        $helper_list->show_toolbar = false;
        $helper_list->simple_header = true;
        $helper_list->identifier = 'file';
        $helper_list->table = 'dirbackup';
        $helper_list->token = Tools::getAdminTokenLite('AdminModules');
        $helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;

        return $helper_list->generateList($dirs, $fields_list);
    }

    /**
     * Undocumented function
     *
     * @param string $file
     * @return void
     */
    public function activeDir($file)
    {
        $dirsDB = $this->getDirDB();
        if ($dirsDB[$file]) {
            $active = ($dirsDB[$file]['active']) ? 0 : 1;
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'rjbackup_dir SET active = ' . $active
            . ', date = \'' . date('Y-m-d H:i:s')
                . '\' WHERE file = \'' . $file . '\'';
        } else {
            $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'rjbackup_dir (file, date, active)
            VALUES (\'' . $file . '\',\'' . date('Y-m-d H:i:s') . '\', 1 )';
        }

        $res = Db::getInstance()->execute($sql);
        return $res;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function renderFormHost()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings host'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Name dir backup data base'),
                        'name' => 'dir_backup_db',
                        'required' => true,
                        'class' => 'fixed-width-lg',
                        'desc' => $this->getDirbackup('R'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Name dir backup files'),
                        'name' => 'dir_backup_file',
                        'required' => true,
                        'class' => 'fixed-width-lg',
                        'desc' => $this->getDirbackup('R', 'F'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Ignore statistics tables'),
                        'name' => 'PS_BACKUP_ALL',
                        'desc' => $this->l('connections, connections_page, connections_source, guest, statssearch.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Drop existing tables during import'),
                        'name' => 'PS_BACKUP_DROP_TABLE',
                        'desc' => $this->l('If enabled, the backup script will drop your tables prior to restoring data.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitConfiBackup';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array($fields_form));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getConfigFieldsValues()
    {
        $id_shop_group = Shop::getContextShopGroupID();
        $id_shop = Shop::getContextShopID();
        if (include(_PS_ROOT_DIR_ . '/app/config/parameters.php')) {
            if (Tools::getValue('dir_backup_db', Configuration::get('dir_backup_db', null, $id_shop_group, $id_shop))) {
                $this->dir_backup_db = Tools::getValue('dir_backup_db', Configuration::get('dir_backup_db', null, $id_shop_group, $id_shop));
            }
            if (Tools::getValue('dir_backup_file', Configuration::get('dir_backup_file', null, $id_shop_group, $id_shop))) {
                $this->dir_backup_file = Tools::getValue('dir_backup_file', Configuration::get('dir_backup_file', null, $id_shop_group, $id_shop));
            }
        }

        return array(
            'dir_backup_db' => $this->dir_backup_db,
            'dir_backup_file' => $this->dir_backup_file,
            'PS_BACKUP_ALL' => Tools::getValue('PS_BACKUP_ALL', Configuration::get('PS_BACKUP_ALL', null, $id_shop_group, $id_shop)),
            'PS_BACKUP_DROP_TABLE' => Tools::getValue('PS_BACKUP_DROP_TABLE', Configuration::get('PS_BACKUP_DROP_TABLE', null, $id_shop_group, $id_shop)),
        );
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function renderFormFtp()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings ftp destination'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    // Select
                    array(
                        'type' => 'select',
                        'label' => $this->l('Protocolo'),
                        'name' => 'ftp_protocol',
                        'options' => array(
                            'query' => array(
                                array(
                                    'tipe_ftp' => 'ftp',
                                    'name' => 'FTP',
                                ),
                                array(
                                    'tipe_ftp' => 'sftp',
                                    'name' => 'SFTP',
                                ),
                            ),
                            'id' => 'tipe_ftp',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('FTP host'),
                        'name' => 'ftp_host',
                        'required' => true,
                        'class' => 'fixed-width-lg',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('FTP port'),
                        'name' => 'ftp_port',
                        'class' => 'fixed-width-lg',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('FTP user'),
                        'name' => 'ftp_user',
                        'required' => true,
                        'class' => 'fixed-width-lg',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('FTP password'),
                        'name' => 'ftp_password',
                        'required' => true,
                        'class' => 'fixed-width-lg',
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('FTP passive mode'),
                        'name' => 'ftp_pasv',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('FTP remote dir'),
                        'name' => 'ftp_remote_dir',
                        'class' => 'fixed-width-lg',
                        'desc' => 'Ejemp: "public/backup" defauld "/"',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitConfigFtp';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getFtpConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array($fields_form));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getFtpConfigFieldsValues()
    {
        $id_shop_group = Shop::getContextShopGroupID();
        $id_shop = Shop::getContextShopID();

        return array(
            'ftp_protocol' => Tools::getValue('ftp_protocol', Configuration::get('ftp_protocol', null, $id_shop_group, $id_shop)),
            'ftp_host' => Tools::getValue('ftp_host', Configuration::get('ftp_host', null, $id_shop_group, $id_shop)),
            'ftp_port' => Tools::getValue('ftp_port', Configuration::get('ftp_port', null, $id_shop_group, $id_shop)),
            'ftp_user' => Tools::getValue('ftp_user', Configuration::get('ftp_user', null, $id_shop_group, $id_shop)),
            'ftp_password' => Tools::getValue('ftp_password', Configuration::get('ftp_password', null, $id_shop_group, $id_shop)),
            'ftp_pasv' => Tools::getValue('ftp_pasv', Configuration::get('ftp_pasv', null, $id_shop_group, $id_shop)),
            'ftp_remote_dir' => Tools::getValue('ftp_remote_dir', Configuration::get('ftp_remote_dir', null, $id_shop_group, $id_shop)),
        );
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function testConnect()
    {

        $dataFTP = $this->getFtpConfigFieldsValues();

        $ftp = new FtpClass(
            $dataFTP['ftp_host'],
            $dataFTP['ftp_port'],
            $dataFTP['ftp_user'],
            $dataFTP['ftp_password'],
            $dataFTP['ftp_pasv'],
            $dataFTP['ftp_protocol'],
            $dataFTP['ftp_remote_dir']
        );

        if (!$ftp->connectFTP()) {
            $this->errors[] = $this->l($ftp->getError());
            return false;
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param string $file
     * @param string $type_dir
     * @return void
     */
    public function sendFtp($file, $type_dir = '')
    {
        if (Tools::strtoupper($type_dir) == 'F') {
            $dir = $this->getDirbackup('', $type_dir);
        } else {
            $dir = $this->getDirbackup();
        }

        if ($this->id) {
            $file = Tools::substr($file, Tools::strlen($dir));
        }

        $backupfile = $dir . $file;

        $this->id = realpath($backupfile);

        $dataFTP = $this->getFtpConfigFieldsValues();

        $ftp = new FtpClass(
            $dataFTP['ftp_host'],
            $dataFTP['ftp_port'],
            $dataFTP['ftp_user'],
            $dataFTP['ftp_password'],
            $dataFTP['ftp_pasv'],
            $dataFTP['ftp_protocol'],
            $dataFTP['ftp_remote_dir']
        );

        if ($ftp->connectFTP()) {
            if (!$ftp->sendFileFTP($this->id, $file)) {
                $this->errors[] = $this->l($ftp->getError());
                return false;
            }
            return true;
        } else {
            $this->errors[] = $this->l($ftp->getError());
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function renderFormCreateBackup()
    {
        $this->context->smarty->assign(
            array(
                'link' => $this->context->link,
            )
        );
        return $this->display(__FILE__, 'rjbackup_form.tpl');
    }

    /**
     * Undocumented function
     *
     * @param string $type_dir
     * @return void
     */
    public function getFilesDirBackup($type_dir = '')
    {
        $backups = array();

        if (Tools::strtoupper($type_dir) == 'F') {
            $dir = $this->getDirbackup('', $type_dir);
        } else {
            $dir = $this->getDirbackup();
        }

        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if (!is_dir($dir . $file) && $file != 'index.php') {
                    $item = array(
                        'file' => $file,
                        'date' => date("d-m-Y H:i:s.", filemtime($dir . $file)),
                        'size' => $this->formatSizeUnits(filesize($dir . $file)),
                    );
                    array_push($backups, $item);
                }
            }
        }
        return $backups;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function renderListBackupDB()
    {
        $backups = $this->getFilesDirBackup();

        $this->context->smarty->assign(
            array(
                'link' => $this->context->link,
                'dir' => $this->getDirbackup('R'),
                'backups' => $backups,
            )
        );

        return $this->display(__FILE__, 'list_db.tpl');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function renderListBackupFiles()
    {
        $backups = $this->getFilesDirBackup('F');

        $this->context->smarty->assign(
            array(
                'link' => $this->context->link,
                'dir' => $this->getDirbackup('R', 'F'),
                'backups' => $backups,
            )
        );

        return $this->display(__FILE__, 'list_files.tpl');
    }

    /**
     * Undocumented function
     *
     * @param int $bytes
     * @return void
     */
    protected function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    /**
     * Undocumented function
     *
     * @param string $type_route
     * @param string $type_dir
     * @return void
     */
    protected function getDirbackup($type_route = '', $type_dir = '')
    {
        $id_shop_group = Shop::getContextShopGroupID();
        $id_shop = Shop::getContextShopID();

        if (Tools::strtoupper($type_dir) == 'F') {
            if (Tools::getValue('dir_backup_file', Configuration::get('dir_backup_file', null, $id_shop_group, $id_shop))) {
                $this->dir_backup_file = Tools::getValue('dir_backup_file', Configuration::get('dir_backup_file', null, $id_shop_group, $id_shop));
            } else {
                $this->error[] = $this->l('The database backup directory has not been configured.');
                return false;
            }

            if ($type_route == 'R') {
                $dir = $this->dir_module_re . $this->name . '/rjbackup_dirs/' . $this->dir_backup_file . '/';
            } else {
                $dir = $this->dir_module_ab . $this->name . '/rjbackup_dirs/' . $this->dir_backup_file . '/';
            }
        } else {
            if (Tools::getValue('dir_backup_db', Configuration::get('dir_backup_db', null, $id_shop_group, $id_shop))) {
                $this->dir_backup_db = Tools::getValue('dir_backup_db', Configuration::get('dir_backup_db', null, $id_shop_group, $id_shop));
            } else {
                $this->error[] = $this->l('The files backup directory has not been configured.');
                return false;
            }

            if ($type_route == 'R') {
                $dir = $this->dir_module_re . $this->name . '/rjbackup_dirs/' . $this->dir_backup_db . '/';
            } else {
                $dir = $this->dir_module_ab . $this->name . '/rjbackup_dirs/' . $this->dir_backup_db . '/';
            }
        }

        return $dir;
    }

    /**
     * Undocumented function
     *
     * @param string $name_dir
     * @return void
     */
    public function createDirBackup($name_dir)
    {
        if (!file_exists($this->dir_module_ab . $this->name . '/rjbackup_dirs/' . $name_dir)) {
            $dir = $this->dir_module_ab . $this->name . '/rjbackup_dirs/' . $name_dir;
            return mkdir($dir, 0755);
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    protected function addBackupFile()
    {
        if (!$dir_backup_file = $this->getDirbackup('', 'F')) {
            return false;
        }

        if (!file_exists($dir_backup_file)) {
            echo "\n" . Context::getContext()->getTranslator()->trans('Unable to create backup file', array(), 'Admin.Advparameters.Notification') . ' "' . addslashes($dir_backup_file) . '"' . "\n";
            return false;
        }

        $pathInfo = pathinfo($this->dir_backup_in);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];

        // Generate some random number, to make it extra hard to guess backup file names
        $rand = dechex(mt_rand(0, min(0xffffffff, mt_getrandmax())));
        $date = time();
        $backupfile = $dir_backup_file . 'backup_' . $date . '-' . $rand . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($backupfile, ZIPARCHIVE::CREATE) === true) {
            $zip->addEmptyDir($dirName);

            $dirsDB = $this->getDirDB();

            foreach ($dirsDB as $dir_active) {
                if ($dir_active['active']) {
                    $this->dirsIgnoreActives[] .= $this->dir_backup_in . '/' . $dir_active['file'];
                }
            }

            if ($this->dir_backup_in == $dirName) {
                if (!$this->addZip($this->dir_backup_in, $zip, 0)) {
                    $this->error[] = $this->l("Fallo al crear zip...") . $this->dir_backup_in;
                }
            } else {
                if (!$this->addZip($this->dir_backup_in, $zip, Tools::strlen("$parentPath/"))) {
                    $this->error[] = $this->l("Fallo al crear zip...") . $this->dir_backup_in;
                }
            }

            $zip->close();

            if (file_exists($backupfile)) {
                $this->id = realpath($backupfile);
                return true;
            } else {
                return false;
            }
        } else {
            $this->error[] = $this->l('ERROR to create ZIP.');
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param string $dir_backup
     * @param string $zip
     * @param int $exclusiveLength
     * @return void
     */
    protected function addZip($dir_backup, &$zip, $exclusiveLength)
    {
        ini_set('max_execution_time', 300);

        $df = opendir($dir_backup);

        while (($file = readdir($df)) !== false) {
            $f = $dir_backup . '/' . $file;
            $localPath = Tools::substr($f, $exclusiveLength);
            if (is_file($f)) {
                $zip->addFile($f, $localPath);
            } elseif (is_dir($f) && !in_array($file, $this->ignore_dir) && !in_array($f, $this->dirsIgnoreActives)) {
                $zip->addEmptyDir($localPath);
                $this->addZip($f, $zip, $exclusiveLength);
            }
        }
        closedir($df);

        return true;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getDirDB()
    {
        $dirs = array();

        $sql = 'SELECT `file` , `active`
                FROM ' . _DB_PREFIX_ . 'rjbackup_dir';

        $rows = Db::getInstance()->executeS($sql);

        foreach ($rows as $row) {
            $dirs[$row['file']] = $row;
        }

        return $dirs;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function delete()
    {
        if (!$this->id || !unlink($this->id)) {
            $this->error = Context::getContext()->getTranslator()->trans('Error deleting', array(), 'Admin.Advparameters.Notification') . ' ' . ($this->id ? '"' . $this->id . '"' :
                Context::getContext()->getTranslator()->trans('Invalid ID', array(), 'Admin.Advparameters.Notification'));

            return false;
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function cronBackup()
    {
        if (!$this->addBackupDB()) {
            return Context::getContext()->getTranslator()->trans('Error backup data base', array(), 'Admin.rjbackup.Error');
        } else {
            if (!$this->sendFtp($this->id)) {
                return Context::getContext()->getTranslator()->trans('Error backup envio data base FTP', array(), 'Admin.rjbackup.Error');
            }
        }

        if (!$this->addBackupFile()) {
            return Context::getContext()->getTranslator()->trans('Error backup files', array(), 'Admin.rjbackup.Error');
        } else {
            if (!$this->sendFtp($this->id, 'F')) {
                return Context::getContext()->getTranslator()->trans('Error backup envio file FTP', array(), 'Admin.rjbackup.Error');
            }
        }

        return '';
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function addBackupDB()
    {

        if (!$dir = $this->getDirbackup()) {
            $this->errors[] = $this->l('The database backup directory has not been configured_');
            return false;
        }

        if (!$this->rjBackupAll) {
            $ignoreInsertTable = array(_DB_PREFIX_ . 'connections', _DB_PREFIX_ . 'connections_page', _DB_PREFIX_
                . 'connections_source', _DB_PREFIX_ . 'guest', _DB_PREFIX_ . 'statssearch',
            );
        } else {
            $ignoreInsertTable = array();
        }

        // Generate some random number, to make it extra hard to guess backup file names
        $rand = dechex(mt_rand(0, min(0xffffffff, mt_getrandmax())));
        $date = time();
        $backupfile = $dir . 'backup_' . $date . '-' . $rand . '.sql';

        // Figure out what compression is available and open the file
        if (function_exists('bzopen')) {
            $backupfile .= '.bz2';
            $fp = @bzopen($backupfile, 'w');
        } elseif (function_exists('gzopen')) {
            $backupfile .= '.gz';
            $fp = @gzopen($backupfile, 'w');
        } else {
            $fp = @fopen($backupfile, 'wb');
        }

        if ($fp === false) {
            echo "\n" . Context::getContext()->getTranslator()->trans('Unable to create backup file', array(), 'Admin.Advparameters.Notification') . ' "' . addslashes($backupfile) . '"' . "\n";
            return false;
        }

        $this->id = realpath($backupfile);

        fwrite($fp, '/* Backup for ' . Tools::getHttpHost(false, false) . __PS_BASE_URI__ . "\n *  at " . date($date) . "\n */\n");
        fwrite($fp, "\n" . 'SET NAMES \'utf8\';');
        fwrite($fp, "\n" . 'SET FOREIGN_KEY_CHECKS = 0;');
        fwrite($fp, "\n" . 'SET SESSION sql_mode = \'\';' . "\n\n");

        // Find all tables
        $tables = Db::getInstance()->executeS('SHOW TABLES');
        $found = 0;
        foreach ($tables as $table) {
            $table = current($table);

            // Skip tables which do not start with _DB_PREFIX_
            if (Tools::strlen($table) < Tools::strlen(_DB_PREFIX_) || strncmp($table, _DB_PREFIX_, Tools::strlen(_DB_PREFIX_)) != 0) {
                continue;
            }

            // Export the table schema
            $schema = Db::getInstance()->executeS('SHOW CREATE TABLE `' . $table . '`');

            if (count($schema) != 1 || !isset($schema[0]['Table']) || !isset($schema[0]['Create Table'])) {
                fclose($fp);
                $this->delete();
                echo Context::getContext()->getTranslator()->trans('An error occurred while backing up. Unable to obtain the schema of %s', array($table), 'Admin.Advparameters.Notification');

                return false;
            }

            fwrite($fp, '/* Scheme for table ' . $schema[0]['Table'] . " */\n");

            if ($this->rjBackupDropTable) {
                fwrite($fp, 'DROP TABLE IF EXISTS `' . $schema[0]['Table'] . '`;' . "\n");
            }

            fwrite($fp, $schema[0]['Create Table'] . ";\n\n");

            if (!in_array($schema[0]['Table'], $ignoreInsertTable)) {
                $data = Db::getInstance()->query('SELECT * FROM `' . $schema[0]['Table'] . '`', false);
                $sizeof = Db::getInstance()->numRows();
                $lines = explode("\n", $schema[0]['Create Table']);

                if ($data && $sizeof > 0) {
                    // Export the table data
                    fwrite($fp, 'INSERT INTO `' . $schema[0]['Table'] . "` VALUES\n");
                    $i = 1;
                    while ($row = Db::getInstance()->nextRow($data)) {
                        $s = '(';

                        foreach ($row as $field => $value) {
                            $tmp = "'" . pSQL($value, true) . "',";
                            if ($tmp != "'',") {
                                $s .= $tmp;
                            } else {
                                foreach ($lines as $line) {
                                    if (strpos($line, '`' . $field . '`') !== false) {
                                        if (preg_match('/(.*NOT NULL.*)/Ui', $line)) {
                                            $s .= "'',";
                                        } else {
                                            $s .= 'NULL,';
                                        }

                                        break;
                                    }
                                }
                            }
                        }
                        $s = rtrim($s, ',');

                        if ($i % 200 == 0 && $i < $sizeof) {
                            $s .= ");\nINSERT INTO `" . $schema[0]['Table'] . "` VALUES\n";
                        } elseif ($i < $sizeof) {
                            $s .= "),\n";
                        } else {
                            $s .= ");\n";
                        }

                        fwrite($fp, $s);
                        ++$i;
                    }
                }
            }
            ++$found;
        }

        fclose($fp);
        if ($found == 0) {
            $this->delete();
            echo Context::getContext()->getTranslator()->trans('No valid tables were found to backup.', array(), 'Admin.Advparameters.Notification');

            return false;
        }

        return true;
    }
}
