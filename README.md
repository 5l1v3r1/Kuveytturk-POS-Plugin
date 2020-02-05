# Kuveytpos
## WooCommerce için Kuveyttürk Sanal Pos Ödeme Yöntemi

### Bu Eklenti Ne İşe Yarar?
Wordpress üzerine kurulan WooCommerce e-ticaret sistemi için, Kuveyttürk bankası tarafından sağlanan sanal POS sistemi FreePOS'u kullanan bir ödeme yöntemi eklemenizi sağlar. Böylece siteniz üzerindeki ödeme sayfasında kredi kartı ile ödeme alabilirsiniz.

### Öncelikle Bilinmesi Gerekenler
Kuveyttürk FreePos sistemi Türk Lirası kabul eder. Bu yüzden WooCommerce'in ayarlarından para birimi olarak TL seçilmesi gerekir. Farklı bir para biriminde çalışan siteniz varsa, ödeme alınırken, toplam tutarın TL cinsine çevrilmesi gereklidir.
Bunun için paylaştığım diğer eklenti sayesinde örneğin dolar fiyatlı çalışan siteniz üzerinden Türk Lirası olarak ödeme almanız mümkün hale geliyor.

### Nasıl Kullanılır?
İndirdiğiniz """kuveytpos""" klasörünü web sitesindeki Wordpress'in plugins klasörüne atın.
Eklentiyi aktif ettikten sonra, WooCommerce ayarlarındaki Ödeme sekmesinde "Kuveyttürk Sanal POS Sistemi" adında yeni bir ödeme yöntemi çıkacak. Bu yöntemi aktif edip yanında "Yönet" butonuna tıklayın.

Açılan sayfada; bankanın sisteminden oluşturduğun API kullanıcı adı ve şifresini girin, bankanın size gönderdiği dökümantasyon dosyasındaki URL adreslerini girin ve ayarları kaydedin.

Dilerseniz öncesinde test etmek için bu alanlara, banka tarafından verilen size verilen test bilgilerini girip test edebilirsiniz.

### TL Harici Bir Para Birimi Kullanıyorum
Bu durumda dosyaların arasında bulunan "TL Göster" isimli eklentiyi kullanabilirsiniz. Bu eklentiyi yükleyip aktif ettikten sonra yönetim panelinde "TL Göster" isminde bir ayar menüsü çıkacak. Bu sayfadan Euro ve Dolar için çevrimde kullanılacak kuru kendiniz belirleyebilirsiniz. Dilerseniz aynı sayfanın altındaki "Kurları Güncelle" butonu ile TCMB üzerinden güncel kurları çekebilirsiniz.

Şu an bu eklenti sadece Euro ve Dolar için yapıldı. Diğer para birimleri için yaptığınız güncellemeleri Pull Request olarak gönderebilirsiniz.

![Kuveytpos](https://raw.githubusercontent.com/muratcesmecioglu/Kuveytturk-POS-Plugin/master/Kuveytpos.png)
