<?php
namespace IjorTengab\IBank;

use IjorTengab\WebCrawler\AbstractWebCrawler;
use IjorTengab\WebCrawler\VisitException;

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
     * Internal property.
     */
    public $username;
    public $password;
    public $account;

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
                    'visit_after' => [
                        'home_page_authenticated' => 'parse_home_page_authenticated',
                        'home_page_anonymous' => 'parse_home_page_anonymous',
                        '404_page' => 'parse_404_page',
                    ],
                ],
                'login_page' => [
                    'visit_after' => [
                        'form_exists' => 'parse_login_page',
                        'home_page_anonymous' => 'parse_home_page_anonymous',
                    ],
                ],
                'login_form' => [
                    'visit_after' => [
                        'home_page_authenticated' => 'parse_home_page_authenticated',
                        'login_error' => 'parse_login_form_error',
                    ],
                ],
                'account_page' => [
                    'visit_after' => [
                        'table_account' => 'parse_account_page',
                    ],
                ],
                'balance_information_page' => [
                    'visit_after' => [
                        'form_exists' => 'parse_account_type_form',
                    ],
                ],
                'account_type_form' => [
                    'visit_after' => [
                        'form_exists' => 'parse_account_number_form',
                    ],
                ],
                'account_number_form' => [
                    'visit_after' => [
                        'table_balance' => 'parse_balance_information_page',
                    ],
                ],
            ],
            'target' => [
                'get_balance' => [
                    [
                        'type' => 'visit',
                        'menu' => 'home_page',
                    ],
                    [
                        'type' => 'visit',
                        'menu' => 'account_page',
                    ],
                    [
                        'type' => 'visit',
                        'menu' => 'balance_information_page',
                    ],
                    [
                        'type' => 'visit',
                        'menu' => 'account_type_form',
                    ],
                    [
                        'type' => 'visit',
                        'menu' => 'account_number_form',
                    ],
                ],
                'get_transaction' => [
                    [
                        'type' => 'visit',
                        'menu' => 'home_page',
                    ],
                    [
                        'type' => 'visit',
                        'menu' => 'account_page',
                    ],
                    [
                        'type' => 'visit',
                        'menu' => 'transaction_history_page',
                    ],
                ],
            ],
        ];
    }

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

    protected function visitAfter()
    {       
        // Hapus url, agar tidak tersimpan di configuration.
        // Karnea url bersifat dinamais.
        $menu_name = $this->step['menu'];
        $this->configuration('menu][' . $menu_name . '][url', null);
    }
    
    /**
     * Memastikan bahwa halaman mengandung indikasi yang dibutuhkan untuk
     * nantinya bisa diparsing sesuai dengan target.
     */
    protected function visitAfterIndicationOf($indication)
    {
        switch ($indication) {
            case '404_page':
                $text = $this->html->find('span#Step1')->text();
                $position = strpos($text, '404');
                return is_int($position);

            case 'home_page_authenticated':
                return ($this->html->find('span#CurrentProfileDisp')->length > 0);

            case 'home_page_anonymous':
                return ($this->html->find('table#Language_table')->length > 0);

            case 'form_exists':
                return ($this->html->find('form')->length > 0);

            case 'login_error':
                return ($this->html->find('#Display_MConError')->length > 0);

            case 'table_balance':
                return ($this->html->find('table[id~=BalanceDisplayTable]')->eq(1)->length > 0);

            case 'table_account':
                return ($this->html->find('table#AccountMenuList_table')->length > 0);
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
            case 'get_transaction':
                $url_account_page = $this->html->find('td a')->eq(0)->attr('href');
                if (empty($url_account_page)) {
                    throw new VisitException('Url for menu "account_page" not found.');
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
            // Apapun targetnya, aktivitasnya sama.
            default:
                // Belum login, maka tambah langkah baru.
                $prepand_steps = [
                    [
                        'type' => 'visit',
                        'menu' => 'login_page',
                        // 'verify' => 'form_exists',
                    ],
                    [
                        'type' => 'visit',
                        'menu' => 'login_form',
                        // 'verify' => 'successful',
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
                    throw new VisitException('Url for menu login_page not found.');
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
            // Apapun targetnya, aktivitasnya sama.
            default:
                $url = $this->html->find('form')->attr('action');
                if (empty($url)) {
                    throw new VisitException('Url for form "login_form" not found.');
                }
                if (empty($this->username) || empty($this->password)) {
                    throw new VisitException('Username and Password required.');
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
                    throw new VisitException('Url for menu "balance_information_page" not found.');
                }
                $this->configuration('menu][balance_information_page][url', $url_balance_information_page);
                break;

            case 'get_transaction':
                $url_transaction_history_page = $this->html->find('td a')->eq(2)->attr('href');
                if (empty($url_transaction_history_page)) {
                    throw new VisitException('Url for menu "transaction_history_page" not found.');
                }
                $this->configuration('menu][transaction_history_page][url', $url_transaction_history_page);
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
                    throw new VisitException('Url for form "account_type_form" not found.');
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
                    throw new VisitException('Url for form "account_number_form" not found.');
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
            case 'get_transaction':
                // 404 terjadi, maka tambah langkah baru.
                // Temukan url login.
                $url = $this->html->find('a#Login')->attr('href');
                if (empty($url)) {
                    throw new VisitException('Url for menu "login_page" not found.');
                }
                $this->configuration('menu][login_page][url', $url);
                $prepand_steps = [
                    [
                        'type' => 'visit',
                        'menu' => 'login_page',
                    ],
                ];
                $this->addStep('prepand', $prepand_steps);
                break;
        }
    }

    /**
     * Todo.
     */
    protected function parseLoginFormError()
    {
        $text = $this->html->find('#Display_MConError')->text();
        $text = preg_replace('/\s\s+/', ' ', $text);
        $text = trim($text);
        throw new VisitException('Login failed. Message: ' . $text);
    }

}
