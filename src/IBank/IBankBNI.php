<?php
namespace IjorTengab\IBank;

use IjorTengab\WebCrawler\AbstractWebCrawler;
use IjorTengab\WebCrawler\RequestException;
use IjorTengab\WebCrawler\VerifyRequestException;

/**
 * Mengimplementasikan Abstract WebCrawler untuk menelusuri internet banking
 * BNI (Bank Negara Indonesia) 1946.
 * Dibuat pada Januari 2015, dilanjutkan pada Desember 2015.
 *
 * @link
 *   http://www.bni.co.id/
 *   https://ibank.bni.co.id
 */
class IBankBNI extends AbstractWebCrawler
{

    use IBankTrait;

    /**
     * @inherit.
     */
    public function defaultCwd()
    {
        return getcwd() . DIRECTORY_SEPARATOR . 'BNI';
    }

    /**
     * @inherit
     */
    public function defaultConfiguration()
    {
        return [
            'menu' => [
                'home_page' => [
                    'url' => 'https://ibank.bni.co.id',
                ],
            ],
            'target' => [
                'get_balance' => [
                    [
                        'type' => 'request',
                        'menu' => 'home_page',
                        'verify' => 'authenticated',
                    ],
                    [
                        'type' => 'request',
                        'menu' => 'account_page',
                        'verify' => 'successful',
                    ],
                    [
                        'type' => 'request',
                        'menu' => 'balance_information_page',
                        'verify' => 'form_exists',
                    ],
                    [
                        'type' => 'request',
                        'menu' => 'account_type_form',
                        'verify' => 'form_exists',
                    ],
                    [
                        'type' => 'request',
                        'menu' => 'account_number_form',
                        'verify' => 'successful',
                    ],
                ],
                'get_transaction_history' => [

                ],
            ],
        ];
    }

    /**
     * Internal property.
     */
    public $username;
    public $password;
    public $account;

    /**
     * Override method.
     *
     * Set property information in object.
     *
     * @param $property string
     *   Parameter dapat bernilai sebagai berikut:
     *   - username
     *     Username for login.
     *   - password
     *     Password for login.
     *   - account
     *     Account Number.
     *   dan property lainnya yang dijelaskan pada parent::set().
     */
    public function set($property, $value)
    {
        switch ($property) {
            case 'username':
            case 'password':
            case 'account':
                $this->{$property} = $value;
                break;
        }
        return parent::set($property, $value);
    }

    /**
     * Override method.
     *
     * Set browser as mobile, and not use curl as library request.
     */
    protected function browserInit()
    {
        parent::browserInit();
        $user_agent_mobile = $this->configuration('user_agent_mobile');
        if (!$user_agent_mobile) {
            $user_agent = $this->browser->getUserAgent('Mobile Browser');
            $this->configuration('user_agent', $user_agent);
            $this->configuration('user_agent_mobile', true);
            $this->browser->options('user_agent', $user_agent);
        }
        $this->browser->curl(false);
    }

    /**
     * Memastikan bahwa halaman saat ini adalah page '404'.
     */
    protected function _is404Page()
    {
        $text = $this->html->find('span#Step1')->text();
        $position = strpos($text, '404');
        return is_int($position);
    }

    /**
     * Verifikasi hasil request menu "home_page" dengan context "authenticated".
     */
    protected function verifyRequestHomePageAuthenticated()
    {
        // Hapus agar tidak tersimpan dalam file configuration.
        $this->configuration('menu][home_page][url', null);

        $indication_authenticated = $this->html->find('span#CurrentProfileDisp');
        $indication_not_authenticated_yet = $this->html->find('table#Language_table');

        if ($indication_authenticated->length > 0) {
            $this->parseHomePageAuthenticated();
        }
        elseif ($indication_not_authenticated_yet->length > 0) {
            $this->parseHomePageAnonymous();
        }
        elseif ($this->_is404Page()) {
            $this->parse404Page();
        }
        else {
            throw new VerifyRequestException('home_page');
        }
    }

    /**
     * Verifikasi hasil request menu "login_page" dengan context "form_exists".
     */
    protected function verifyRequestLoginPageFormExists()
    {
        // Hapus agar tidak tersimpan dalam file configuration.
        $this->configuration('menu][login_page][url', null);

        $indication_form_exists = $this->html->find('form');
        if ($indication_form_exists->length > 0) {
            $this->parseLoginPage();
        }
        else {
            throw new VerifyRequestException('login_page');
        }
    }

    /**
     * Verifikasi hasil request menu "login_page" dengan context "successful".
     */
    protected function verifyRequestLoginFormSuccessful()
    {
        // Hapus agar tidak tersimpan dalam file configuration.
        $this->configuration('menu][login_form][url', null);

        $indication_failed = $this->html->find('#Display_MConError');
        $indication_authenticated = $this->html->find('span#CurrentProfileDisp');
        if ($indication_authenticated->length > 0) {
            $this->parseHomePageAuthenticated();
        }
        elseif ($indication_failed->length > 0) {
            $text = $indication_failed->text();
            throw new RequestException('Login failed. Message: ' . $text);
        }
        else {
            throw new VerifyRequestException('login_form');
        }
    }

    /**
     * Verifikasi hasil request menu "account_page" dengan context "successful".
     */
    protected function verifyRequestAccountPageSuccessful()
    {
        // Hapus agar tidak tersimpan dalam file configuration.
        $this->configuration('menu][account_page][url', null);

        $indication_successful = $this->html->find('table#AccountMenuList_table');
        if ($indication_successful->length > 0) {
            $this->parseAccountPage();
        }
        else {
            throw new VerifyRequestException('account_page');
        }
    }

    /**
     * Verifikasi hasil request menu "balance_information_page" dengan context
     * "form_exists".
     */
    protected function verifyRequestBalanceInformationPageFormExists()
    {
        // Hapus agar tidak tersimpan dalam file configuration.
        $this->configuration('menu][balance_information_page][url', null);

        $indication_form_exists = $this->html->find('form');
        if ($indication_form_exists->length > 0) {
            $this->parseAccountTypeForm();
        }
        else {
            throw new VerifyRequestException('balance_information_page');
        }

    }

    /**
     * Verifikasi hasil request menu "account_type_form" dengan context
     * "form_exists".
     */
    protected function verifyRequestAccountTypeFormFormExists()
    {
        // Hapus agar tidak tersimpan dalam file configuration.
        $this->configuration('menu][account_type_form][url', null);

        $indication_form_exists = $this->html->find('form');
        if ($indication_form_exists->length > 0) {
            $this->parseAccountNumberForm();
        }
        else {
            throw new VerifyRequestException('account_type_form');
        }
    }

    /**
     * Verifikasi hasil request menu "account_number_form" dengan context
     * "successful".
     */
    protected function verifyRequestAccountNumberFormSuccessful()
    {
        // Hapus agar tidak tersimpan dalam file configuration.
        $this->configuration('menu][account_number_form][url', null);

        $indication_table_balance = $this->html->find('table[id~=BalanceDisplayTable]')->eq(1);
        if ($indication_table_balance->length > 0) {
            $this->parseBalanceInformationPage();
        }
        else {
            throw new VerifyRequestException('account_number_form');
        }
    }

    /**
     * Verifikasi hasil request menu "login_page" dengan context
     * "home_page_anonymous".
     */
    protected function verifyRequestLoginPageHomePageAnonymous()
    {
        // Hapus agar tidak tersimpan dalam file configuration.
        $this->configuration('menu][login_page][url', null);
        $indication_not_authenticated_yet = $this->html->find('table#Language_table');
        if ($indication_not_authenticated_yet->length > 0) {
            $this->parseHomePageAnonymous();
        }
        else {
            throw new VerifyRequestException('login_page');
        }
    }

    /**
     * Parsing menu "home_page" context "authenticated" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function parseHomePageAuthenticated()
    {
        switch ($this->target) {
            case 'get_balance':
                $url_account_page = $this->html->find('td a')->eq(0)->attr('href');
                if (empty($url_account_page)) {
                    throw new RequestException('Url for menu "account_page" not found.');
                }
                $this->configuration('menu][account_page][url', $url_account_page);
                break;
        }
    }

    /**
     * Parsing menu "home_page" context "anonymous" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function parseHomePageAnonymous()
    {
        switch ($this->target) {
            case 'get_balance':
                // Belum login, maka tambah langkah baru.
                $prepand_steps = [
                    [
                        'type' => 'request',
                        'menu' => 'login_page',
                        'verify' => 'form_exists',
                    ],
                    [
                        'type' => 'request',
                        'menu' => 'login_form',
                        'verify' => 'successful',
                    ],
                ];
                $this->addStep('prepand', $prepand_steps);

                // Cari tahu bahasa situs, mungkin nanti bakal berguna.
                if ($this->html->find('span.Languageleftselect')->length) {
                    $this->configuration('language', 'ID-ID');
                }
                elseif ($this->html->find('span.Languagerightselect')->length) {
                    $this->configuration('language', 'EN-US');
                }

                // Cari url untuk ke halaman login_page.
                $url_login_page = $this->html->find('#RetailUser')->attr('href');
                if (empty($url_login_page)) {
                    throw new RequestException('Url for menu login_page not found.');
                }
                $this->configuration('menu][login_page][url', $url_login_page);
                break;
        }
    }

    /**
     * Parsing menu "login_page" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function parseLoginPage()
    {
        switch ($this->target) {
            case 'get_balance':
                $url = $this->html->find('form')->attr('action');
                if (empty($url)) {
                    throw new RequestException('Url for form "login_form" not found.');
                }
                if (empty($this->username) || empty($this->password)) {
                    throw new RequestException('Username and Password required.');
                }
                $fields = $this->html->find('form')->extractForm();
                $fields['__AUTHENTICATE__'] = 'Login';
                $fields['CorpId'] = $this->username;
                $fields['PassWord'] = $this->password;
                $this->configuration('menu][login_form][url', $url);
                $this->configuration('menu][login_form][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "account_page" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function parseAccountPage()
    {
        switch ($this->target) {
            case 'get_balance':
                $url_balance_information_page = $this->html->find('td a')->eq(0)->attr('href');
                if (empty($url_balance_information_page)) {
                    throw new RequestException('Url for menu "balance_information_page" not found.');
                }
                $this->configuration('menu][balance_information_page][url', $url_balance_information_page);
                break;
        }
    }

    /**
     * Parsing menu "account_type_form" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function parseAccountTypeForm()
    {
        switch ($this->target) {
            case 'get_balance':
                $url = $this->html->find('form')->attr('action');
                if (empty($url)) {
                    throw new RequestException('Url for form "account_type_form" not found.');
                }
                $fields = $this->html->find('form')->extractForm();
                $submit = $this->html->find('form')->extractForm('input[type=submit]');
                // Buang semua input submit kecuali 'AccountIDSelectRq'.
                $keep = 'AccountIDSelectRq';
                unset($submit[$keep]);
                $fields = array_diff_assoc($fields, $submit);
                // Pilih pada Tabungan dan Giro dengan value = OPR.
                $fields['MAIN_ACCOUNT_TYPE'] = 'OPR';
                $this->configuration('menu][account_type_form][url', $url);
                $this->configuration('menu][account_type_form][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "account_number_form" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function parseAccountNumberForm()
    {
        switch ($this->target) {
            case 'get_balance':
                $url = $this->html->find('form')->attr('action');
                if (empty($url)) {
                    throw new RequestException('Url for form "account_number_form" not found.');
                }
                $fields = $this->html->find('form')->extractForm();
                $submit = $this->html->find('form')->extractForm('input[type=submit]');
                // Buang semua input submit kecuali 'BalInqRq'.
                $keep = 'BalInqRq';
                unset($submit[$keep]);
                $fields = array_diff_assoc($fields, $submit);
                // Todo, beri support bagi satu akun yang terdiri dari
                // banyak nomor rekening.
                $this->configuration('menu][account_number_form][url', $url);
                $this->configuration('menu][account_number_form][fields', $fields);

                break;
        }
    }

    /**
     * Parsing menu "balance_information_page" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function parseBalanceInformationPage()
    {
        switch ($this->target) {
            case 'get_balance':
                // Get Balance.
                $indication_table_balance = $this->html->find('table[id~=BalanceDisplayTable]')->eq(1);
                $span = $indication_table_balance->find('tr#Row5_5 td#Row5_5_column2 div > span');
                $balance = $span->text();
                $this->result = $balance;

                // Keep information of home_page
                $form = $this->html->find('form');
                $url = $form->attr('action');
                $fields = $form->extractForm();
                $submit = $form->extractForm('input[type=submit]');
                $keep = '__HOME__';
                unset($submit[$keep]);
                $fields = array_diff_assoc($fields, $submit);
                $this->configuration('menu][home_page][url', $url);
                $this->configuration('menu][home_page][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "404_page" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function parse404Page()
    {
        switch ($this->target) {
            case 'get_balance':
                // 404 terjadi, maka tambah langkah baru.
                // Temukan url login.
                $url = $this->html->find('a#Login')->attr('href');
                if (empty($url)) {
                    throw new RequestException('Url for menu "login_page" not found.');
                }
                $this->configuration('menu][login_page][url', $url);
                $prepand_steps = [
                    [
                        'type' => 'request',
                        'menu' => 'login_page',
                        'verify' => 'home_page_anonymous',
                    ],
                ];
                $this->addStep('prepand', $prepand_steps);
                break;
        }
    }
}
