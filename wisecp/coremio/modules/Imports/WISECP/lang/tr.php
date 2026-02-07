<?php 
return [
    'name'           => "WISECP'den İçeri Aktarma",
    'page-title'     => "WISECP'den İçeri Aktarma",
    'notice-message' => "<p style=\"text-align:center;\"><strong>Bir ba\xc5\x9fka WISECP'sistemindeki verileri kolayl\xc4\xb1kla kendi WISECP sisteminize aktarabilirsiniz.</strong></p>\n<ul>\n<li>Sunucu kaynaklar\xc4\xb1n\xc4\xb1z\xc4\xb1n yetersizli\xc4\x9fi nedeniyle i\xc5\x9flem yar\xc4\xb1da kesilebilir. Temiz veritaban\xc4\xb1 \xc3\xbczerine tekrar i\xc3\xa7e aktarma yapabilmek i\xc3\xa7in mevcut WISECP veri taban\xc4\xb1n\xc4\xb1z\xc4\xb1n bir yede\xc4\x9fini saklay\xc4\xb1n\xc4\xb1z.</li>\n<li>\xc4\xb0\xc5\x9flem yapmadan \xc3\xb6nce, hostunuzun \"memory size, max_execution_time, max_input_time, max_input_vars\" vb. gibi limitlerinin y\xc3\xbcksek oldu\xc4\x9fundan emin olun.</li>\n<li>Bu mod\xc3\xbcl, di\xc4\x9fer WISECP veri taban\xc4\xb1 \xc3\xbczerinde herhangi bir i\xc5\x9flem yapmaz, sadece verileri kopyalar.</li>\n<li>\xc4\xb0\xc5\x9flem s\xc3\xbcresi, verilerinizin boyutuna g\xc3\xb6re de\xc4\x9fi\xc5\x9fkenlik g\xc3\xb6sterecektir. L\xc3\xbctfen i\xc5\x9flem tamamlanana kadar bekleyin. </li>\n </ul>",
    'encryption-key'    => "Kullanıcı Şifreleme Anahtarı",
    'encryption-key-info' => "WISECP'ye ait <b>coremio/configuration/crypt.php</b> dosyasındaki, 'user' değerine ait KEY bilgisini giriniz.",
    'encryption-key2'   => "Sistem Şifreleme Anahtarı",
    'encryption-key2-info' => "WISECP'ye ait <b>coremio/configuration/crypt.php</b> dosyasındaki, 'system' değerine ait KEY bilgisini giriniz.",
    'same-database-error' => "Aynı sunucuda aynı veri tabanına içe aktarma yapılamaz.",
];
