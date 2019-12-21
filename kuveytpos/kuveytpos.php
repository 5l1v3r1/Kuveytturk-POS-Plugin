<?php
/*
 * Plugin Name: WooCommerce KuveytTurk Sanal POS Ödeme Sistemi
 * Plugin URI: https://murat.cesmecioglu.net/kuveytpos
 * Description: WooCommerce için KuveytTürk Sanal POS Ödeme Sistemi
 * Author: Murat "MrT" Çeşmecioğlu
 * Author URI: http://murat.cesmecioglu.net
 * Version: 1.0
 */

add_action('plugins_loaded', 'kuveytpos_init_gateway_class', 0);
add_action('plugins_loaded', 'kuveytpos_kontrol', 1);

function kuveytpos_kontrol() {
  global $woocommerce;
  $guncelparabirimi = get_woocommerce_currency(); //Eur USD TRY
  
  if ( $guncelparabirimi != "TRY" ) {
    if ( is_mrtcurrency_active() ) {
      $eklentiayar = get_option( 'mrt_currency_options' );
      $eklentionoff = $eklentiayar['eklenti_onoff'];
      if ($eklentionoff == 0) {
        add_action( 'admin_notices', 'kuveytpos_uyari_goster' );
      }
    } else {
      add_action( 'admin_notices', 'kuveytpos_uyari_goster' );
    }
  }
}

function kuveytpos_uyari_goster() {
  ?>
    <div class="update-nag notice">
        <p>WooCommerce için seçili para birimi Türk Lirasından farklı fakat Kuveyttürk Pos Sistemi için ödeme tutarının Türk Lirası cinsinden olması gerekli. Eklentinin düzgün çalışması için "TL Göster" eklentisini etkinleştirin ve eklenti ayarlarından açık duruma getirin ya da WooCommerce ayarlarından para birimini düzenleyin.</p>
    </div>
    <?php
}

function is_mrtcurrency_active(){
    if( in_array('mrt-currency/mrt-currency.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        return true;
    }
    return false;
}

function kuveytpos_init_gateway_class(){
  if(!class_exists('WC_Payment_Gateway')) return;

  /*======================================
  =            Gateway Ekleme            =
  ======================================*/
  
  function kuveytpos_add_gateway_class($methods) {
    $methods[] = 'WC_KuveytPos_Gateway';
    return $methods;
  } //
  add_filter('woocommerce_payment_gateways', 'kuveytpos_add_gateway_class' );
  
  /*=====================================
  =            Gateway Class            =
  =====================================*/
  
  class WC_KuveytPos_Gateway extends WC_Payment_Gateway{

    /*----------  Gateway Ayarları  ----------*/
    
    function __construct(){ 
      $this->id = 'kvtposodeme';
      $this->method_title = 'KuveytTürk Sanal POS';
      $this->method_description = 'KuveytTürk Sanal POS Sistemi'; 
      $this->has_fields = false;
      $this->init_form_fields();
      $this->init_settings();
      $this->liveurl = ''; //ToDo?
      $this->msg['message'] = "";
      $this->msg['class'] = "";

      $this->title = $this->settings['title'];
      $this->description = $this->settings['description'];

      $this->merchant_id = $this->settings['merchant_id'];
      $this->store_id = $this->settings['store_id'];
      $this->api_user = $this->settings['api_user'];
      $this->api_pass = $this->settings['api_pass'];
      $this->url_paygate = $this->settings['url_paygate'];
      $this->url_provisiongate = $this->settings['url_provisiongate'];

      add_action('woocommerce_api_' . $this->id, array( &$this, 'posodeme_callback') ); //Bankadan callback fonksiyonu
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //Admin sayfası kayıt
      add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page')); //Banka yönlendirmesi için ara sayfa
    } //

    /*----------  Gateway Ayar Sayfası  ----------*/
    
    function admin_options(){ //Admin sayfası
      echo '<h3>KuveytTürk Sanal Pos</h3>';
      echo '<p>KuveytTürk Sanal Pos ile ödeme alabilirsiniz.</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html(); //Alt bölümdeki girişleri forma çevirir
      echo '</table>';
      echo '<div><hr><small><a href="https://murat.cesmecioglu.net">Murat Çeşmecioğlu</a><br><sub>KuveytTürk Sanal POS WooCommerce Ödeme Modülü</sub></small></div>';
    } //

    function init_form_fields(){
      $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Aktif / Pasif',
                    'type' => 'checkbox',
                    'label' => 'KuveytTürk Sanal Pos Modülünü Aktive Edin.',
                    'default' => 'no'),

                'title' => array(
                    'title' => 'Görünür Adı:',
                    'type'=> 'text',
                    'description' => 'Ödeme sayfasında müşterilerinizin göreceği ödeme yöntemi adı',
                    'default' => 'Kredi Kartı İle Ödeme'),
                'description' => array(
                    'title' => 'Açıklama:',
                    'type' => 'textarea',
                    'description' => 'Ödeme sayfasında müşterilerinizin göreceği ödeme yöntemi açıklaması',
                    'default' => 'Kredi kartınızla güvenle ödeme yapın.'),

                'merchant_id' => array(
                    'title' => 'Müşteri No',
                    'type' => 'text',
                    'description' => 'Bankanın verdiği müşteri numarası'),
                'store_id' => array(
                    'title' => 'Mağaza No',
                    'type' => 'text',
                    'description' =>  'Bankanın verdiği mağaza kodu'),
                'api_user' => array(
                    'title' => 'API Kullanıcı Adı',
                    'type' => 'text',
                    'description' =>  'Bankanın verdiği API kullanıcı adı'),
                'api_pass' => array(
                    'title' => 'API Kullanıcı Şifresi',
                    'type' => 'text',
                    'description' =>  'Bankanın verdiği API kullanıcı şifresi'),

                'url_paygate' => array(
                    'title' => 'Sanal Pos Güvenli Ödeme Noktası Adresi',
                    'type' => 'text',
                    'description' =>  'Bankanın verdiği ödeme noktası adresi'),
                'url_provisiongate' => array(
                    'title' => 'Sanal Pos 3D Model Ödeme Onaylama Adresi',
                    'type' => 'text',
                    'description' =>  'Bankanın verdiği ödeme onaylama adresi')
      );
    } //
    
    /*----------  Kredi Kartı Giriş Formu  ----------*/
        
    function payment_fields(){
        //if($this->description) echo wpautop(wptexturize($this->description)); //Ayarlar bölümündeki açıklamayı ödeme yönteminde gösterir
        echo $this->generate_kuveytpos_form($order); //Kredi kartı giriş formunu gösterir
    } //

    /*----------  Kredi Kartı Giriş Formu  ----------*/
    
    function generate_kuveytpos_form($order_id){
      return '
      <div class="payment_box payment_method_' . $this->id . '">
      <p class="form-row form-row-wide" id="cc_isim" data-priority="">
        <label for="cc_isim" class="">Kart Üzerindeki İsim <abbr class="required" title="gerekli">*</abbr></label>
        <span class="woocommerce-input-wrapper">
          <input type="text" class="input-text " name="cc_isim" id="cc_isim" placeholder="" value="" required>
        </span>
      </p>
      
      <p class="form-row form-row-wide" id="cc_numara" data-priority="">
        <label for="cc_numara" class="">Kart Numarası <abbr class="required" title="gerekli">*</abbr></label>
        <span class="woocommerce-input-wrapper">
          <input type="text" class="input-text " name="cc_numara" id="cc_numara" placeholder="" value="" pattern="\d+" maxlength="16" required>
        </span>
      </p>
      
      <p class="form-row form-row-wide" id="cc_skt" data-priority="">
        <label for="cc_skt" class="" style="width:100%; clear:both">Son Kullanma Tarihi<abbr class="required" title="gerekli">*</abbr></label>
        <span class="woocommerce-input-wrapper">
          <select required name="cc_sktay" id="cc_sktay" class="select" style="height:40px; float:left; margin: 0 10px 0 0; width: 100px; -webkit-appearance: menulist;" data-allow_clear="true" data-placeholder="Ay" tabindex="-1" aria-hidden="true">
            <option value="">Ay</option>
            <option value="01">1</option>
            <option value="02">2</option>
            <option value="03">3</option>
            <option value="04">4</option>
            <option value="05">5</option>
            <option value="06">6</option>
            <option value="07">7</option>
            <option value="08">8</option>
            <option value="09">9</option>
            <option value="10">10</option>
            <option value="11">11</option>
            <option value="12">12</option>
          </select>
          
          <select required name="cc_sktyil" id="cc_sktyil" class="select" style="height:40px; float:left; width: 100px; -webkit-appearance: menulist;" data-allow_clear="true" data-placeholder="Yıl" tabindex="-1" aria-hidden="true">
            <option value="">Yıl</option>
            <option value="2019">2019</option>
            <option value="2020">2020</option>
            <option value="2021">2021</option>
            <option value="2022">2022</option>
            <option value="2023">2023</option>
            <option value="2024">2024</option>
            <option value="2025">2025</option>
            <option value="2026">2026</option>
            <option value="2027">2027</option>
            <option value="2028">2028</option>
            <option value="2029">2029</option>
            <option value="2030">2030</option>
            <option value="2031">2031</option>
            <option value="2032">2032</option>
            <option value="2033">2033</option>
            <option value="2034">2034</option>
            <option value="2035">2035</option>
          </select>
        </span>
      </p>
      
      <p class="form-row form-row-wide" id="cc_cvv" data-priority="">
        <label for="cc_cvv" class="">CVV2<abbr class="required" title="gerekli">*</abbr></label>
        <span class="woocommerce-input-wrapper">
          <input type="text" class="input-text " name="cc_cvv" id="cc_cvv" pattern="[0-9]{3}" maxlength="3" placeholder="" value="" required>
        </span>
      </p>
      
      </div>
      ';
    } //

    /*----------  Ödeme İşlemi  ----------*/
    
    function process_payment($order_id){ 
      //Ödeme yap butonuna basınca yapılacaklar
      global $woocommerce;
      $order = new WC_Order( $order_id );

      $order->add_order_note("Ödeme İşlemi Başladı: " . $order->get_order_number() ); //Sipariş Takibi
      
      $redirect_url = add_query_arg( 'wc-api', $this->id, get_site_url() );

      /*----------  Kredi Kartı Bilgileri Kontorlü  ----------*/
      $hata = 0;

      /*----------  Bilgileri al  ----------*/
      $Name = $this->get_data('cc_isim');
      $CardNumber = $this->get_data('cc_numara');
      $CardCVV2 = $this->get_data('cc_cvv');
      $CardExpireDateMonth = $this->get_data('cc_sktay');
      $CardExpireDateYear = $this->get_data('cc_sktyil');

      /*----------  İsim Kontrolü  ----------*/
      if ( empty( $CardNumber ) || strlen($CardNumber) < 2 ) {
        wc_add_notice( "Kredi kartı üzerindeki isim hatalı", 'error' );
        $hata = 1;
      }
      /*----------  Kart No Kontrolü  ----------*/
      $CardNumber = str_replace( array( ' ', '-' ), '', $CardNumber );
      if ( empty( $CardNumber ) || ! ctype_digit( $CardNumber ) || ! $this->luhn_check($CardNumber)) {
        wc_add_notice( "Kredi kartı numarası hatalı", 'error' );
        $hata = 1;
      }
      /*----------  CVV2 Kontrolü  ----------*/
      if ( empty( $CardCVV2 ) || ! ctype_digit( $CardCVV2 ) ) {
        wc_add_notice( "CVV numarası hatalı", 'error' );
        $hata = 1;
      } 
      
      /*----------  Kart SKT Kontrolü  ----------*/
      $currentYear = date( 'Y' );
      if ( ! ctype_digit( $CardExpireDateMonth ) || ! ctype_digit( $CardExpireDateYear ) ||
      $CardExpireDateMonth > 12 ||
      $CardExpireDateMonth < 1 ||
      $CardExpireDateYear < $currentYear ||
      $CardExpireDateYear > $currentYear + 16
      ) {
        wc_add_notice( "Kartın son kullanma tarihi hatalı", 'error' );
        $hata = 1;
      } else {
        $CardExpireDateMonth = str_pad( $CardExpireDateMonth, 2, '0', STR_PAD_LEFT ); //Kart SKT ayın 2 karakterli olduğundan emin ol
        $CardExpireDateYear = substr( $CardExpireDateYear, -2 ); //Kart SKT yılın son iki rakamını al
      }
      
      $MerchantOrderId = $order_id ; //WC tarafındaki sipariş kodu. Banka ile ietişimde siparişi takip etmek için bankaya gönderiyoruz
      
      /*----------  İşlem Tutarı Ayarlama  ----------*/
      $toplami = $order->get_total();
      $guncelparabirimi = get_woocommerce_currency(); //EUR USD TRY
      $tlfiyat = number_format($toplami, 2, "", "");

      if ( is_mrtcurrency_active() ) { 
        $eklentiayar = get_option( 'mrt_currency_options' );
        $eklentionoff = $eklentiayar['eklenti_onoff'];
        if ($eklentionoff == 1) {
          $tlsembol = get_woocommerce_currency_symbol( "TRY" );
          $mrtkureklentisi = get_option( 'mrt_currency_options' );
          $eurokur = $mrtkureklentisi['kur_euro'];
          $dolarkur = $mrtkureklentisi['kur_dolar'];
          if ($guncelparabirimi == "EUR") {
            $tlfiyat = number_format($toplami * $eurokur, 2, "", "");
          }
          if ($guncelparabirimi == "USD") {
            $tlfiyat = number_format($toplami * $dolarkur, 2, "", "");
          }
        }
      }

      $Amount = intval($tlfiyat); //İşlem tutarı. Kuruş cinsinden gönderilecek
      $order->add_order_note("POS Tutar: " . $Amount); //Sipariş Takibi
      
      $OkUrl = $redirect_url; //Başarılı sonuç alınırsa, yönlendirilecek sayfa
      $FailUrl = $redirect_url;//Başarısız sonuç alınırsa, yönlendirilecek sayfa
      $CustomerId = $this->merchant_id; //Müsteri Numarasi
      $MerchantId = $this->store_id; //Magaza Kodu
      $UserName = $this->api_user; // Web Yönetim ekranlarından olusturulan api rollü kullanici
      $Password = $this->api_pass; // Web Yönetim ekranlarından olusturulan api rollü kullanici şifre
      $HashedPassword = base64_encode(sha1($Password,"ISO-8859-9")); //md5($Password);
      $HashData = base64_encode(sha1($MerchantId.$MerchantOrderId.$Amount.$OkUrl.$FailUrl.$UserName.$HashedPassword , "ISO-8859-9"));
      
      /*----------  Banka Mesajı Hazırla  ----------*/
      $xml= '<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
      .'<APIVersion>1.0.0</APIVersion>'
      .'<OkUrl>'.$OkUrl.'</OkUrl>'
      .'<FailUrl>'.$FailUrl.'</FailUrl>'
      .'<HashData>'.$HashData.'</HashData>'
      .'<MerchantId>'.$MerchantId.'</MerchantId>'
      .'<CustomerId>'.$CustomerId.'</CustomerId>'
      .'<UserName>'.$UserName.'</UserName>'
      .'<CardNumber>'.$CardNumber.'</CardNumber>'
      .'<CardExpireDateYear>'.$CardExpireDateYear.'</CardExpireDateYear>'
      .'<CardExpireDateMonth>'.$CardExpireDateMonth.'</CardExpireDateMonth>'
      .'<CardCVV2>'.$CardCVV2.'</CardCVV2>'
      .'<CardHolderName>'.$Name.'</CardHolderName>'
      .'<CardType>MasterCard</CardType>'
      .'<BatchID>0</BatchID>'
      .'<TransactionType>Sale</TransactionType>'
      .'<InstallmentCount>0</InstallmentCount>'
      .'<Amount>'.$Amount.'</Amount>'
      .'<DisplayAmount>'.$Amount.'</DisplayAmount>'
      .'<CurrencyCode>0949</CurrencyCode>'
      .'<MerchantOrderId>'.$MerchantOrderId.'</MerchantOrderId>'
      .'<TransactionSecurity>3</TransactionSecurity>'
      .'</KuveytTurkVPosMessage>';

      session_start();
      $_SESSION['curlxml'] = $xml; //Sonraki sayfaya session ile taşıyalım
      
      if ($hata == 0) { //Hata yoksa banka ile iletişime geç ve işleme devam et
        return array(
          'result' => 'success',
          'redirect' => $order->get_checkout_payment_url( true )
        );
      
      } elseif ($hata == 1) { //Hata varsa dur
        return array(
          'result' => 'failure',
          'redirect' => ''
        );
      }
    } //

    /*----------  Banka Yönlendirme Sayfası  ----------*/
    
    function receipt_page($order){
      echo '<p>Siparişiniz için teşekkür ederiz.</p>'; 
      
      ob_start();
      session_start();
      global $woocommerce;
      $order = new WC_Order( $order_id );
      $curlxml = $_SESSION['curlxml'];
      try {
        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: '. strlen($curlxml)) );
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_URL, $this->url_paygate); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlxml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);  
        curl_close($ch);
      }
      catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
      }
      echo($data);
      echo "<p>Ödeme için bankaya yönlendiriliyorsunuz</p>";
      error_reporting(E_ALL);
      ini_set("display_errors", 1);
    } //

    /*----------  Banka Dönüşü Yapılacak İşlemler  ----------*/

    function posodeme_callback() {
      header( 'HTTP/1.1 200 OK' );
      global $woocommerce;

      $AuthenticationResponse=$_POST["AuthenticationResponse"]; //Bankadan gelen cevabı al
      $RequestContent = urldecode($AuthenticationResponse); //Decode et
      $xxml=simplexml_load_string($RequestContent) or die("Hata: Banka dönüş hatası");
      //print_r($xxml); //DEBUG
      
      if( $xxml->ResponseCode == '00') { //Banka kartı doğruladı, ödeme alma işlemine başla
        $order = new WC_Order( intval($xxml->VPosMessage->MerchantOrderId) ); //Siparişi bul

        $order->add_order_note( 'Kart Doğrulandı: (' . $xxml->ResponseCode . ') ' . $xxml->ResponseMessage ); //Sipariş Takibi

        /*----------  İşlem Tutarı Ayarlama - Tekrar  ----------*/
        $toplami = $order->get_total();
        $guncelparabirimi = get_woocommerce_currency(); //Eur USD TRY
        $tlfiyat = number_format($toplami, 2, "", "");

        if ( is_mrtcurrency_active() ) { 
          $eklentiayar = get_option( 'mrt_currency_options' );
          $eklentionoff = $eklentiayar['eklenti_onoff'];
          if ($eklentionoff == 1) {
            $tlsembol = get_woocommerce_currency_symbol( "TRY" );
            $mrtkureklentisi = get_option( 'mrt_currency_options' );
            $eurokur = $mrtkureklentisi['kur_euro'];
            $dolarkur = $mrtkureklentisi['kur_dolar'];
            if ($guncelparabirimi == "EUR") {
              $tlfiyat = number_format($toplami * $eurokur, 2, "", "");
            }
            if ($guncelparabirimi == "USD") {
              $tlfiyat = number_format($toplami * $dolarkur, 2, "", "");
            }
          }
        }

        $Amount = intval($tlfiyat); //İşlem tutarı. Kuruş cinsinden gönderilecek

        $MerchantOrderId = $xxml->VPosMessage->MerchantOrderId; //Banka ile ietişimde siparişi takip etmek için bankaya gönderiyoruz
        $MD = $xxml->MD; //Banka doğrulama 
        $Type = "Sale";

        $CustomerId = $this->merchant_id; //Müsteri Numarasi
        $MerchantId = $this->store_id; //Magaza Kodu
        $UserName = $this->api_user; // Web Yönetim ekranlarından olusturulan api rollü kullanici
        $Password = $this->api_pass; // Web Yönetim ekranlarından olusturulan api rollü kullanici şifre

        $HashedPassword = base64_encode(sha1($Password,"ISO-8859-9")); //md5($Password);
        $HashData = base64_encode(sha1($MerchantId.$MerchantOrderId.$Amount.$UserName.$HashedPassword , "ISO-8859-9"));

        $xml='<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
          <APIVersion>1.0.0</APIVersion>
          <HashData>'.$HashData.'</HashData>
          <MerchantId>'.$MerchantId.'</MerchantId>
          <CustomerId>'.$CustomerId.'</CustomerId>
          <UserName>'.$UserName.'</UserName>
          <TransactionType>Sale</TransactionType>
          <InstallmentCount>0</InstallmentCount>
          <CurrencyCode>0949</CurrencyCode>
          <Amount>'.$Amount.'</Amount>
          <MerchantOrderId>'.$MerchantOrderId.'</MerchantOrderId>
          <TransactionSecurity>3</TransactionSecurity>
          <KuveytTurkVPosAdditionalData>
          <AdditionalData>
            <Key>MD</Key>
            <Data>'.$MD.'</Data>
          </AdditionalData>
        </KuveytTurkVPosAdditionalData>
        </KuveytTurkVPosMessage>';

        try {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: '. strlen($xml)) );
          curl_setopt($ch, CURLOPT_POST, true); 
          curl_setopt($ch, CURLOPT_HEADER, false);
          curl_setopt($ch, CURLOPT_URL, $this->url_provisiongate );
          curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          $data = curl_exec($ch);  
          curl_close($ch);
        }
        catch (Exception $e) {
          echo 'Caught exception: ',  $e->getMessage(), "\n";
        }

        $otorizecevap = simplexml_load_string($data);
        //print_r($otorizecevap); //DEBUG
        error_reporting(E_ALL);
        ini_set("display_errors", 1);
      
        if( $otorizecevap->ResponseCode == '00') { //Bankadan Ödeme Yapıldı Yanıtı Geldi
          $order->add_order_note( 'Ödeme Tamamlandı: (' . $otorizecevap->ResponseCode . ') ' . $otorizecevap->ResponseMessage); //Sipariş Takibi
          $order->payment_complete(); //Siparişin durumunu 'ödeme yapıldı' olarak değiştir
          wp_safe_redirect( $order->get_checkout_order_received_url() ); //Müşteriyi sonuç sayfasına yönlendir
        } 
        else { //Banka ödemesinde hata yanıtı geldi
          //echo "Banka ödemesinde hata yanıtı geldi";
          $order->add_order_note( 'Ödeme Banka Hatası: ('.$otorizecevap->ResponseCode.') ' . $otorizecevap->ResponseMessage); //Sipariş Takibi
          wc_add_notice( 'Ödeme Sırasında Banka Hata Mesajı: ' . $otorizecevap->ResponseMessage, 'error' ); //Sonuç sayfasında gösterilecek mesaj
          wp_safe_redirect( ywraq_get_accepted_quote_page( $order ) ); //Müşteriyi ödeme sayfasına yönlendir
        }
      } else { //Banka kart doğrulaması sırasında hata yanıtı geldi
        //echo "Banka kart doğrulaması sırasında hata yanıtı geldi";
        $order = new WC_Order( intval($xxml->MerchantOrderId) ); //Siparişi bul
        $order->add_order_note( 'Ödeme Sırasında Banka Hata Mesajı: ('.$xxml->ResponseCode.') ' . $xxml->ResponseMessage); //Sipariş Takibi
        wc_add_notice( 'Banka Hata Mesaji: ' . $xxml->ResponseMessage, 'error' ); //Sonuç sayfasında gösterilecek mesaj
        wp_safe_redirect( ywraq_get_accepted_quote_page( $order ) ); //Müşteriyi ödeme sayfasına yönlendir
      }
      die();
    } //

    /*----------  Kullanılan Fonksyionlar  ----------*/
    
    function get_data( $name ) {
      if ( isset( $_POST[ $name ] ) ) {
        return sanitize_text_field( $_POST[ $name ] );
      }
      return null;
    } //
    
    function luhn_check($number) {
      $number=preg_replace('/\D/', '', $number);
      $number_length=strlen($number);
      $parity=$number_length % 2;
      $total=0;
      for ($i=0; $i<$number_length; $i++) {
        $digit=$number[$i];
        if ($i % 2 == $parity) {
          $digit*=2;
          if ($digit > 9) {
            $digit-=9;
          }
        }
        $total+=$digit;
      }
      return ($total % 10 == 0) ? TRUE : FALSE;
    } //
  } // Class WC_KuveytPos_Gateway
} // Function kuveytpos_init_gateway_class