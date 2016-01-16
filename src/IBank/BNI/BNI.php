<?php

namespace IjorTengab\IBank\BNI;

use IjorTengab\ActionWrapper\ModuleInterface;
use IjorTengab\WebCrawler\AbstractWebCrawler;
use IjorTengab\WebCrawler\WebCrawlerTrait;
use IjorTengab\WebCrawler\VisitException;
use IjorTengab\IBank\WebCrawlerModuleTrait;

/**
 * Class sebagai handler BNI, menggunakan abstract Web Crawler dan
 * mengimplementasikan interface ModuleInterface.
 *
 * @link
 *   http://www.bni.co.id/
 *   https://ibank.bni.co.id
 */
class BNI extends AbstractWebCrawler implements ModuleInterface
{
    use WebCrawlerModuleTrait, WebCrawlerTrait;

    const BNI_MAIN_URL = 'https://ibank.bni.co.id';

    /**
     * Internal property.
     */
    public $username;
    public $password;
    public $account;
    // Dapat bernilai
    // string, untuk nantinya akan diconvert oleh fungsi strtotime();
    // integer, diasumsikan sebagai unix timestamp.
    // array dengan key 'start' dan 'end'
    public $range;

    /**
     * @inherit.
     */
    public function defaultCwd()
    {
        return getcwd() . DIRECTORY_SEPARATOR . '.ibank' . DIRECTORY_SEPARATOR . 'BNI';
    }

    /**
     * @inherit
     */
    public function defaultConfiguration()
    {
        return [
            'menu' => $this->defaultConfigurationMenu(),
            'target' => [
                'get_balance' => $this->defaultConfigurationTargetGetBalance(),
                'get_transaction' => $this->defaultConfigurationTargetGetTransaction(),
            ],
        ];
    }

    /**
     * Referensi menu.
     */
    protected function defaultConfigurationMenu()
    {
        return [
            'home_page' => [
                'url' => self::BNI_MAIN_URL,
                'visit_after' => [
                    'home_page_authenticated' => 'bni_parse_home_page_authenticated',
                    'home_page_anonymous' => 'bni_parse_home_page_anonymous',
                    '404_page' => 'bni_parse_404_page',
                ],
            ],
            'login_page' => [
                'visit_after' => [
                    'home_page_anonymous' => 'bni_parse_home_page_anonymous',
                    'form_exists' => 'bni_parse_login_page',
                ],
            ],
            'login_form' => [
                'visit_after' => [
                    'home_page_authenticated' => 'bni_parse_home_page_authenticated',
                    'login_error' => 'bni_parse_login_form_error',
                ],
            ],
            'account_page' => [
                'visit_after' => [
                    'table_account' => 'bni_parse_account_page',
                ],
            ],
            'balance_inquiry_page' => [
                'visit_after' => [
                    'form_exists' => 'bni_parse_account_type_form',
                ],
            ],
            'account_type_form' => [
                'visit_after' => [
                    // 'table_search_option' => 'bni_parse_account_number_form',
                    'form_exists' => 'bni_parse_account_number_form',
                ],
            ],
            'account_number_form' => [
                'visit_after' => [
                    '404_page' => [
                        'reset_execute',
                        'bni_reset_execute',
                    ],
                    'table_balance' => 'bni_parse_balance_inquiry_page',
                    'mini_statement_page' => 'bni_parse_mini_statement_page',
                ],
            ],
            'transaction_history_page' => [
                'visit_after' => [
                    'form_exists' => 'bni_parse_account_type_form',
                ],
            ],
            'mini_statement_page' => [
                'visit_after' => [
                    'mini_statement_select_account_number' => 'bni_parse_account_number_form',
                ],
            ],
        ];
    }

    /**
     * Referensi target "get_balance".
     */
    protected function defaultConfigurationTargetGetBalance()
    {
        return [
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
                'menu' => 'balance_inquiry_page',
            ],
            [
                'type' => 'visit',
                'menu' => 'account_type_form',
            ],
            [
                'type' => 'visit',
                'menu' => 'account_number_form',
            ],
        ];
    }

    /**
     * Referensi target "get_transaction".
     */
    protected function defaultConfigurationTargetGetTransaction()
    {
        return [
            [
                'type' => 'visit',
                'menu' => 'home_page',
            ],
            [
                'type' => 'visit',
                'menu' => 'account_page',
            ],
            [
                'handler' => 'bni_check_range',
                'null' => [
                    'append_step' => [
                        [
                            'type' => 'visit',
                            'menu' => 'mini_statement_page',
                        ],
                        [
                            'type' => 'visit',
                            'menu' => 'account_number_form',
                        ],
                    ],
                ],
                'other' => [
                    'append_step' => [
                        [
                            'type' => 'visit',
                            'menu' => 'bakwan',
                        ],
                        [
                            'type' => 'visit',
                            'menu' => 'cucian',
                        ],
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

    /**
     *
     */
    protected function visitAfter()
    {
        // Hapus url, agar tidak tersimpan di configuration.
        // Karena url bersifat dinamis.
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

            case 'mini_statement_select_account_number':
                return ($this->html->find('select[name=MiniStmt]')->length > 0);

            case 'mini_statement_page':
                return ($this->html->find('input[name=PageName][value=OperMiniAccIDSelectRq]')->length > 0);
        }
    }

    /**
     * Parsing menu "home_page" context "authenticated" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function bniParseHomePageAuthenticated()
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
    protected function bniParseHomePageAnonymous()
    {
        switch ($this->target) {
            // Apapun targetnya, aktivitasnya sama.
            default:
                // Belum login, maka tambah langkah baru.
                $prepand_steps = [
                    [
                        'type' => 'visit',
                        'menu' => 'login_page',
                    ],
                    [
                        'type' => 'visit',
                        'menu' => 'login_form',
                    ],
                ];
                $this->addStep('prepand', $prepand_steps);

                // Cari tahu bahasa situs, penting untuk parsing yang
                // terkait bahasa.
                if ($this->html->find('span.Languageleftselect')->length) {
                    $this->configuration('language', 'id');
                }
                elseif ($this->html->find('span.Languagerightselect')->length) {
                    $this->configuration('language', 'en');
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
    protected function bniParseLoginPage()
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
    protected function bniParseAccountPage()
    {
        switch ($this->target) {
            case 'get_balance':
                $url_balance_inquiry_page = $this->html->find('td a')->eq(0)->attr('href');
                if (empty($url_balance_inquiry_page)) {
                    throw new VisitException('Url for menu "balance_inquiry_page" not found.');
                }
                $this->configuration('menu][balance_inquiry_page][url', $url_balance_inquiry_page);
                break;

            case 'get_transaction':
                $url_mini_statement_page = $this->html->find('td a')->eq(1)->attr('href');
                $url_transaction_history_page = $this->html->find('td a')->eq(2)->attr('href');
                // Simpan pada temporary.
                $this->configuration('temporary][url_mini_statement_page', $url_mini_statement_page);
                $this->configuration('temporary][url_transaction_history_page', $url_transaction_history_page);

                // if (empty($url_transaction_history_page)) {
                    // throw new VisitException('Url for menu "transaction_history_page" not found.');
                // }

                break;
        }
    }

    /**
     * Parsing menu "account_type_form" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function bniParseAccountTypeForm()
    {
        switch ($this->target) {
            case 'get_balance':
            case 'get_transaction':
                $form = $this->html->find('form');
                $url = $form->attr('action');
                if (empty($url)) {
                    throw new VisitException('Url for form "account_type_form" not found.');
                }
                $fields = $form->preparePostForm('AccountIDSelectRq');
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
    protected function bniParseAccountNumberForm()
    {
        switch ($this->target) {
            case 'get_balance':
                $form = $this->html->find('form');
                $url = $form->attr('action');
                if (empty($url)) {
                    throw new VisitException('Url for form "account_number_form" not found.');
                }
                $fields = $form->preparePostForm('BalInqRq');
                // Todo, support multi account number.
                // $fields['acc1'] = '';
                $this->configuration('menu][account_number_form][url', $url);
                $this->configuration('menu][account_number_form][fields', $fields);
                break;

            case 'get_transaction':
                $form = $this->html->find('form');
                $url = $form->attr('action');
                if (empty($url)) {
                    throw new VisitException('Url for form "account_number_form" not found.');
                }
                $fields = $form->preparePostForm('Go');
                // Cari nomor rekening.
                $value = null;
                foreach ($fields['MiniStmt'] as $number) {
                    if (strpos($number, $this->account) !== false) {
                        $value = $number;
                    }
                }
                $fields['MiniStmt'] = $value;
                $this->configuration('menu][account_number_form][url', $url);
                $this->configuration('menu][account_number_form][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "balance_inquiry_page" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function bniParseBalanceInquiryPage()
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
                $fields = $form->preparePostForm('__HOME__');
                $this->configuration('menu][home_page][url', $url);
                $this->configuration('menu][home_page][fields', $fields);
                break;
        }
    }

    /**
     * Parsing menu "404_page" sesuai dengan kebutuhan
     * pada property $target.
     */
    protected function bniParse404Page()
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
     *
     */
    protected function bniResetExecute()
    {
        $this->configuration('menu][home_page][url', self::BNI_MAIN_URL);
    }

    /**
     * Todo.
     */
    protected function bniParseLoginFormError()
    {
        $text = $this->html->find('#Display_MConError')->text();
        $text = preg_replace('/\s\s+/', ' ', $text);
        $text = trim($text);
        throw new VisitException('Login failed. Message: ' . $text);
    }

    /**
     *
     */
    protected function bniCheckRange()
    {
        $key = (null === $this->range) ? 'null' : 'other';
        $append_step = $this->step[$key]['append_step'];
        $this->addStep('append', $append_step);

        switch ($key) {
            case 'null':
                $this->configuration('menu][mini_statement_page][url', $this->configuration('temporary][url_mini_statement_page'));
                break;

            case 'other':
                $this->configuration('menu][transaction_history_page][url', $this->configuration('temporary][url_transaction_history_page'));
                break;
        }
    }

    /**
     *
     */
    protected function bniParseMiniStatementPage()
    {
        switch ($this->target) {
            case 'get_transaction':
                $language = $this->configuration('language');
                $tables = $this->html->find('div#TitleBar > table')->extractTable(true);
                if (empty($tables)) {
                    throw new VisitException('Table for Statement not found.');
                }
                $transactions = [];
                while ($table = array_shift($tables)) {
                    if (isset($table[0]) && isset($table[1])) {
                        $info = $language == 'id' ? $this->bniTranslate($table[0]) : $table[0];
                        $value = $table[1];
                        switch ($info) {
                            case 'Transaction Date':
                                $transaction = [];
                                $transaction['date'] = $value;
                                break;

                            case 'Transaction Remarks':
                                $transaction['detail'] = $value;
                                break;

                            case 'Amount type':
                                $transaction['type'] = $value;
                                break;

                            case 'Amount':
                                $transaction['amount'] = $value;
                                break;

                            case 'Account Balance':
                                $transaction['balance'] = $value;
                                $transactions[] = $transaction;
                                break;
                        }
                    }
                }
                // Set to result.
                $this->result = $transactions;

                // Keep information of home_page
                $form = $this->html->find('form');
                $url = $form->attr('action');
                $fields = $form->preparePostForm('__HOME__');
                // Cari nomor rekening.
                $value = null;
                foreach ($fields['MiniStmt'] as $number) {
                    if (strpos($number, $this->account) !== false) {
                        $value = $number;
                    }
                }
                $fields['MiniStmt'] = $value;
                $this->configuration('menu][home_page][url', $url);
                $this->configuration('menu][home_page][fields', $fields);
                break;
        }
    }

    /**
     * Translate dari indonesia ke inggiris.
     */
    protected function bniTranslate($string)
    {
        return isset($this->bniString()[$string]) ? $this->bniString()[$string] : $string;
    }

    /**
     *
     */
    protected function bniString()
    {
        return [
            'Tanggal Transaksi' => 'Transaction Date',
            'Uraian Transaksi' => 'Transaction Remarks',
            'Tipe' => 'Amount type',
            'Jumlah Pembayaran' => 'Amount',
            'Saldo' => 'Account Balance',
        ];
    }
}
