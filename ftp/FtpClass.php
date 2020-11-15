<?php
/**
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
 */

class FtpClass
{
    private $ftp_host;
    private $ftp_port;
    private $ftp_user;
    private $ftp_password;
    private $ftp_pasv;
    private $ftp_remote_dir;
    private $conn_id;
    private $ftp_protocol;
    private $error;

    /**
     * Undocumented function
     *
     * @param string $ftp_host
     * @param int $ftp_port
     * @param string $ftp_user
     * @param string $ftp_password
     * @param boolean $ftp_pasv
     * @param string $ftp_protocol
     * @param string $ftp_remote_dir
     */
    public function __construct(
        $ftp_host,
        $ftp_port,
        $ftp_user,
        $ftp_password,
        $ftp_pasv = true,
        $ftp_protocol = 'FTP',
        $ftp_remote_dir = ''
    ) {
        $this->ftp_protocol = Tools::strtoupper($ftp_protocol);
        $this->ftp_host = $ftp_host;
        $this->ftp_port = empty($ftp_port) ? 21 : $ftp_port;
        $this->ftp_password = $ftp_password;
        $this->ftp_user = $ftp_user;
        $this->ftp_pasv = $ftp_pasv;
        $this->ftp_remote_dir = $ftp_remote_dir;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function connectFTP()
    {
        if ($this->ftp_protocol == 'FTP') {
            if (!function_exists("ftp_connect")) {
                $this->error = 'php ftp_connect library missing ' . $this->ftp_host;
                return false;
            }

            if (!$this->conn_id = ftp_connect($this->ftp_host, $this->ftp_port)) {
                $this->error[] = 'Error connecting to hosting' . $this->ftp_host;
                return false;
            }

            if (!ftp_login($this->conn_id, $this->ftp_user, $this->ftp_password)) {
                ftp_close($this->conn_id);
                $this->error[] = 'Error connecting to USER - PASS';
                return false;
            }

            if ($this->ftp_pasv) {
                ftp_pasv($this->conn_id, true);
            }

            return true;
        } else {
            return $this->connectSFTP();
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    private function connectSFTP()
    {
        if (!function_exists("ssh2_connect")) {
            $this->error = 'php ssh2_connect library missing ' . $this->ftp_host;
            return false;
        }

        if (!$this->conn_id = ssh2_connect($this->ftp_host, $this->ftp_port)) {
            $this->error = 'Error connecting to hosting ' . $this->ftp_host;
            return false;
        }

        if (!ssh2_auth_password($this->conn_id, $this->ftp_user, $this->ftp_password)) {
            $this->error = 'Error connecting to USER - PASS';
            return false;
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param string $archivo_local
     * @param string $archivo_remoto
     * @return void
     */
    public function sendFileFTP($archivo_local, $archivo_remoto)
    {
        if ($this->ftp_protocol == 'FTP') {
            $archivo_remoto = $this->validaRemoteDir($archivo_remoto);
            if (!ftp_put($this->conn_id, $archivo_remoto, $archivo_local, FTP_BINARY)) {
                $this->error = 'Error sending the file, check the FTP settings';
                ftp_close($this->conn_id);
                return false;
            }
            ftp_close($this->conn_id);
            return true;
        } else {
            return $this->sendFileSFTP($archivo_local, $archivo_remoto);
        }
    }

    /**
     * Undocumented function
     *
     * @param string $archivo_local
     * @param string $archivo_remoto
     * @return void
     */
    public function sendFileSFTP($archivo_local, $archivo_remoto)
    {
        $archivo_remoto = $this->validaRemoteDir($archivo_remoto);

        if (!ssh2_scp_send($this->conn_id, $archivo_local, $archivo_remoto, 0644)) {
            $this->error = 'Error sending the file, check the FTP settings';
            ssh2_exec($this->conn_id, 'exit');
            return false;
        }

        ssh2_exec($this->conn_id, 'exit');
        return true;
    }

    /**
     * Undocumented function
     *
     * @param string $archivo_remoto
     * @return void
     */
    private function validaRemoteDir($archivo_remoto)
    {
        if ($this->ftp_remote_dir != '') {
            if (Tools::substr($this->ftp_remote_dir, -1, 1) === '/') {
                return $this->ftp_remote_dir . $archivo_remoto;
            } else {
                return $this->ftp_remote_dir . '/' . $archivo_remoto;
            }
        }
        return $archivo_remoto;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function obtenerRuta()
    {
        $Directorio = ftp_pwd($this->conn_id);

        ftp_close($this->conn_id);

        return $Directorio;
    }
}
