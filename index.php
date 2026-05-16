<?php
//header("Refresh: 15");
///////////////////////////////////////////////////////////////  2  //////////////////////////////////////////////////////////////////
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if(isset($_POST['tel']) && isset($_POST['mac']) && isset($_POST['gateName']) && isset($_POST['tok']) && isset( $_POST['authaction']) && isset($_POST['redir'])) {

  $tel      = $_POST['tel'];
  $mac      = $_POST['mac'];
  $gateName = $_POST['gateName'];
  $tok      = $_POST['tok'];
  $auth     = $_POST['authaction'];
  $redir    = $_POST['redir'];

//  var_dump($mac);

  $valid_user = explode('?', $auth);
  $valid_user = $valid_user[0].'?tok='.$tok.'&redir='.$redir;

  $tel = str_replace('(', "", $tel);
  $tel = str_replace(')', "", $tel);
  $tel = str_replace('-', "", $tel);

  $link   = mysqli_connect('localHost', 'gaa', 'gaa123456', 'hotspot') or die("conn Err");

  $query  = "INSERT INTO `tmp` (`tel`, `mac`, `gateName`, `tok`, `authaction`) VALUES ('$tel', '$mac', '$gateName', '$tok', '$auth')";
  $result = mysqli_query($link, $query);
  mysqli_close($link);
}

/////////////////////////////////////////////////   3    ////////////////////////////////////////////////////

if(isset($_GET['authaction']) && isset($_GET['gatewayname']) && isset($_GET['tok']) && isset($_GET['mac']) && isset($_GET['redir'])){
  $authaction = $_GET['authaction'];
  $gateName   = $_GET['gatewayname'];
  $token      = $_GET['tok'];
  $mac        = $_GET['mac'];
  $redir      = $_GET['redir'];

  $link   = mysqli_connect('localHost', 'gaa', 'gaa123456', 'hotspot') or die("conn Err");
  
  // Файл для хранения индекса ротации
  $index_file = 'rotation_index.txt';
  
  // Получаем все записи с контентом
  $query = "SELECT * FROM contents WHERE sys_id = 2";
  $result_contents = mysqli_query($link, $query);
  
  $all_contents = array();
  while($row = mysqli_fetch_assoc($result_contents)) {
    $all_contents[] = $row;
  }
  
  // Выбираем следующий контент
  if(count($all_contents) > 0) {
    // Читаем текущий индекс из файла
    $current_index = 0;
    if(file_exists($index_file)) {
      $current_index = (int)file_get_contents($index_file);
    }
    
    // Берем запись по текущему индексу
    $content_data = $all_contents[$current_index];
    
    // Увеличиваем индекс для следующего раза
    $next_index = ($current_index + 1) % count($all_contents);
    
    // Сохраняем новый индекс
    file_put_contents($index_file, $next_index);
    
    $logo_path = !empty($content_data['logo_name']) ? 'img/logos/' . $content_data['logo_name'] : 'img/logos/wifi_marketing.jpg';
    $banner_path = !empty($content_data['banner_name']) ? 'img/banners/' . $content_data['banner_name'] : 'img/banners/allo_final1.jpeg';
    $video_path = !empty($content_data['video_name']) ? 'img/video/' . $content_data['video_name'] : 'img/video/head_phone.mp4';
    $telegram_url = !empty($content_data['btn_url']) ? $content_data['btn_url'] : 'https://t.me/Allo_tomsk';
  } else {
    // Если нет записей в contents, используем дефолтные пути
    $logo_path = 'img/logos/wifi_marketing.jpg';
    $banner_path = 'img/banners/allo_final1.jpeg';
    $video_path = 'img/video/head_phone.mp4';
    $telegram_url = 'https://t.me/Allo_tomsk';
  }

  $query  = "SELECT * FROM `users` WHERE `mac` LIKE '$mac'";
  $result = mysqli_query($link, $query) or die('query err');
  $arr    = mysqli_fetch_array($result);
  mysqli_close($link);

  if($arr['mac'] == $mac){
    $action = $authaction;
    $action = explode("?", $action);
    $action = $action[0].'?tok='.$token.'&redir='.$redir;
    $pageName = $banner_path;
    $stat_tg ='http://10.200.10.1:8080/denis/tg_stat.php?mac='.$mac.'&gateName='.$gateName;
    $loadStat ='http://10.200.10.1:8080/denis/stat.php?gateName='.$gateName.'&pageName='.$pageName.'&mac='.$mac;

    echo "<html>
           <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <link rel='stylesheet' href='style_button.css'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
            <title>Регистрация в сети</title>
            <style>
              body, html {
                margin: 0;
                padding: 0;
                height: 100%;
                width: 100%;
                overflow: hidden;
              }
              
              .image-container {
                position: relative;
                width: 100%;
                height: 100vh;
                overflow: hidden;
              }
              
              .background-image {
                width: 100%;
                height: 100%;
                object-fit: cover;
              }
              
              .telegram-btn-container {
                position: absolute;
                bottom: 80px;
                left: 0;
                right: 0;
                text-align: center;
                z-index: 10;
              }
              
              .telegram-btn {
                display: inline-block;
                background-color: #0088cc;
                color: white;
                padding: 15px 35px;
                border-radius: 12px;
                text-decoration: none;
                font-size: 20px;
                font-weight: bold;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                border: 2px solid #ffffff;
              }
              
              .telegram-btn i {
                margin-right: 10px;
                font-size: 24px;
              }
            </style>
           </head>
           <body>
             <div class='image-container'>
               <img src='$banner_path' class='background-image' alt='Allo Background'>
               <div class='telegram-btn-container'>
                 <a href='$telegram_url' onclick='myFunction()' class='telegram-btn'>
                   <i class='fab fa-telegram'></i> Telegram
                 </a>
               </div>
             </div>

              <form name='tok'>
               <input name='tok' type='hidden' value='$token'>
              </form>

              <script>
              window.addEventListener('load', function() {
               try{
                let formData = new FormData(document.forms.tok);
                let xhr = new XMLHttpRequest();
                xhr.open('GET', '$action', false);
                xhr.send(formData);
               }catch{
                console.log('ok');
               }
              });
             </script>

            <script>
             function myFunction() {
              let xhr = new XMLHttpRequest();
              xhr.open('GET', '$stat_tg');
              xhr.send();
             }
            </script>
            <script>
              window.addEventListener('load', function() {
               let xhr = new XMLHttpRequest();
               xhr.open('GET', '$loadStat');
               xhr.send();
              });
            </script>
           </body>
          </html>";

/////////////////////////////////////////////////////////  1   ////////////////////////////////////////////////////////////////////////

  }else{
    $pageName = $logo_path;
    $loadStat ='http://10.200.10.1:8080/denis/stat.php?gateName='.$gateName.'&pageName='.$pageName.'&mac='.$mac;
    
    // Чтение текста из файла
    $instructionText = "В случае если у вас не открывается номеронабиратель телефона при нажатии кнопки позвонить, или если вы регистрируетесь не с мобильного телефона, то после введения телефона и установки галочки \"принимаю условия соглашения\" нажмите кнопку \"позвонить\". Откройте приложение телефон и позвоните на номер +7(952)-890-09-40 вручную. После этих действий просто обновите страницу авторизации в браузере. После этого ваше устройство должно получить доступ к сети интернет. Звонок бесплатный, и необходим для того чтобы система зарегистриовала ваше устройство в базе данных абонентов. Это действие требуется только один раз, в дальнейшем при подключении ранее зарегистрированного устройства, будет открываться страница с рекламными материалами, с этого момента ваше устройство уже имеет доступ к сети, можете закрыть браузер и открыть нужное вам сетевое приложение, или открыть нужный сайт.";
    
    echo"<html>
          <head>
           <meta charset='UTF-8'>
           <link rel='stylesheet' href='style.css'>
           <meta name='viewport' content='width=device-width, initial-scale=1.0'>
           <title>Регистрация в сети</title>
           <style>
             body, html {
               margin: 0;
               padding: 0;
               height: 100%;
               width: 100%;
               overflow: hidden;
               font-family: Arial, sans-serif;
               background: #000;
             }
             
             /* ШАГ 1: Приветствие - АККУРАТНОЕ ЦЕНТРИРОВАНИЕ */
             .step-1 {
               position: absolute;
               top: 0;
               left: 0;
               width: 100%;
               height: 100%;
               background: #000;
               z-index: 30;
               display: flex;
               flex-direction: column;
               align-items: center;
               justify-content: center;
               padding: 20px;
               box-sizing: border-box;
             }
             
             .content-wrapper {
               display: flex;
               flex-direction: column;
               align-items: center;
               justify-content: center;
               width: 100%;
               max-width: 500px;
             }
             
             .wifi-logo {
               width: 85%;
               max-width: 320px;
               height: auto;
               margin: 0 auto 40px;
               display: block;
             }
             
             .start-btn {
               background: #00FF22;
               color: white;
               border: none;
               border-radius: 50px;
               padding: 15px 35px;
               font-size: 19px;
               font-weight: bold;
               cursor: pointer;
               box-shadow: 0 6px 20px rgba(0, 255, 34, 0.4);
               width: 280px;
               max-width: 90%;
               margin: 0 auto;
               display: block;
               transition: all 0.3s;
               text-align: center;
               white-space: nowrap;
               overflow: hidden;
               text-overflow: ellipsis;
             }
             
             .start-btn:hover {
               transform: translateY(-2px);
               box-shadow: 0 8px 25px rgba(0, 255, 34, 0.5);
             }
             
             .instruction {
               color: white;
               font-size: 17px;
               margin-top: 35px;
               text-align: center;
               line-height: 1.5;
               max-width: 320px;
               padding: 0 10px;
             }
             
             /* ШАГ 2: Видео */
             .step-2 {
               position: absolute;
               top: 0;
               left: 0;
               width: 100%;
               height: 100%;
               background: #000;
               z-index: 20;
               display: none;
             }
             
             .video-wrapper {
               width: 100%;
               height: 100%;
               display: flex;
               justify-content: center;
               align-items: center;
             }
             
             .video-player {
               width: 100%;
               height: 100%;
               max-height: 100vh;
               object-fit: cover;
             }
             
             /* ШАГ 3: Форма - ОПУЩЕНА НА 20px */
             .step-3 {
               position: absolute;
               top: 0;
               left: 0;
               width: 100%;
               height: 100%;
               background: #000;
               z-index: 10;
               display: none;
             }
             
             .bg-image {
               width: 100%;
               height: 100%;
               object-fit: cover;
               position: absolute;
               top: 0;
               left: 0;
               opacity: 0.7;
             }
             
             .form-overlay {
               position: absolute;
               bottom: 50px; /* БЫЛО 30px, СТАЛО 50px (опустили на 20px) */
               left: 15px;
               right: 15px;
               background: rgba(255, 255, 255, 0.95);
               padding: 14px 16px;
               border-radius: 12px;
               box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.2);
               z-index: 11;
               max-width: 400px;
               margin: 0 auto;
               box-sizing: border-box;
             }
             
             .my-form {
               display: flex;
               flex-direction: column;
               gap: 12px;
               width: 100%;
             }
             
             .input {
               width: 100%;
             }
             
             .phone_label {
               display: block;
               text-align: center;
               margin-bottom: 4px;
               font-size: 13px;
               color: #333;
               font-weight: bold;
             }
             
             .input input {
               width: 100%;
               padding: 10px;
               font-size: 15px;
               border: 2px solid #ddd;
               border-radius: 7px;
               box-sizing: border-box;
               text-align: center;
             }
             
             .check_box {
               display: flex;
               align-items: center;
               gap: 6px;
               font-size: 12px;
               color: #333;
               padding: 0 3px;
               line-height: 1.3;
             }
             
             .check_box input[type='checkbox'] {
               width: 16px;
               height: 16px;
               accent-color: #00FF22;
               flex-shrink: 0;
             }
             
             .agreement-link {
               color: #0066cc;
               text-decoration: underline;
               cursor: pointer;
               font-weight: bold;
               margin-left: 2px;
             }
             
             .agreement-link:hover {
               color: #004499;
             }
             
             .button {
               width: 100%;
               margin-top: 3px;
             }
             
             .call-button {
               width: 93%;
               background-color: #00FF22;
               color: #FFFFFF;
               font-size: 16px;
               font-weight: bold;
               padding: 12px;
               border: none;
               border-radius: 7px;
               cursor: pointer;
               box-shadow: 0 3px 8px rgba(0, 255, 34, 0.3);
               text-decoration: none;
               display: block;
               text-align: center;
             }
             
             .hidden {
               display: none;
             }
             
             /* МОДАЛЬНОЕ ОКНО ИНСТРУКЦИИ (НОВОЕ) */
             .instruction-modal-overlay {
               position: fixed;
               top: 0;
               left: 0;
               width: 100%;
               height: 100%;
               background: rgba(0, 0, 0, 0.85);
               z-index: 50;
               display: flex;
               justify-content: center;
               align-items: center;
               padding: 15px;
               box-sizing: border-box;
             }
             
             .instruction-modal-content {
               background: white;
               border-radius: 15px;
               max-width: 500px;
               width: 100%;
               max-height: 85vh;
               overflow-y: auto;
               padding: 20px;
               box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
               position: relative;
             }
             
             .instruction-modal-header {
               font-size: 20px;
               font-weight: bold;
               margin-bottom: 15px;
               color: #333;
               text-align: center;
               padding-bottom: 10px;
               border-bottom: 1px solid #eee;
             }
             
             .instruction-modal-body {
               font-size: 14px;
               line-height: 1.5;
               color: #444;
               margin-bottom: 20px;
             }
             
             .instruction-modal-body p {
               margin: 10px 0;
             }
             
             .instruction-modal-footer {
               text-align: center;
               padding-top: 15px;
               border-top: 1px solid #eee;
             }
             
             .instruction-modal-btn {
               background: #00FF22;
               color: white;
               border: none;
               border-radius: 8px;
               padding: 12px 30px;
               font-size: 16px;
               font-weight: bold;
               cursor: pointer;
               margin-top: 10px;
               box-shadow: 0 4px 10px rgba(0, 255, 34, 0.3);
               transition: all 0.3s;
             }
             
             .instruction-modal-btn:hover {
               background: #00DD22;
               transform: translateY(-2px);
               box-shadow: 0 6px 15px rgba(0, 255, 34, 0.4);
             }
             
             /* МОДАЛЬНОЕ ОКНО СОГЛАШЕНИЯ */
             .modal-overlay {
               position: fixed;
               top: 0;
               left: 0;
               width: 100%;
               height: 100%;
               background: rgba(0, 0, 0, 0.7);
               z-index: 1000;
               display: none;
               justify-content: center;
               align-items: center;
               padding: 20px;
               box-sizing: border-box;
             }
             
             .modal-content {
               background: white;
               border-radius: 15px;
               max-width: 500px;
               width: 100%;
               max-height: 80vh;
               overflow-y: auto;
               padding: 25px;
               box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
               position: relative;
             }
             
             .modal-header {
               font-size: 20px;
               font-weight: bold;
               margin-bottom: 20px;
               color: #333;
               text-align: center;
               padding-bottom: 15px;
               border-bottom: 1px solid #eee;
             }
             
             .modal-body {
               font-size: 14px;
               line-height: 1.6;
               color: #444;
             }
             
             .modal-body h3 {
               font-size: 16px;
               margin: 15px 0 10px;
               color: #333;
             }
             
             .modal-body p {
               margin: 10px 0;
             }
             
             .modal-body ul {
               margin: 10px 0;
               padding-left: 20px;
             }
             
             .modal-body li {
               margin: 5px 0;
             }
             
             .modal-close {
               position: absolute;
               top: 15px;
               right: 15px;
               background: #ff4444;
               color: white;
               border: none;
               border-radius: 50%;
               width: 30px;
               height: 30px;
               font-size: 18px;
               cursor: pointer;
               display: flex;
               align-items: center;
               justify-content: center;
             }
             
             .modal-close:hover {
               background: #cc0000;
             }
             
             .modal-footer {
               margin-top: 20px;
               text-align: center;
               padding-top: 15px;
               border-top: 1px solid #eee;
             }
             
             .modal-accept-btn {
               background: #00FF22;
               color: white;
               border: none;
               border-radius: 5px;
               padding: 10px 25px;
               font-size: 16px;
               cursor: pointer;
               margin-top: 10px;
             }
           </style>
          </head>

          <body>
           <script src='js/jquery.min.js'></script>
           <script src='js/jquery.maskedinput.js'></script>

            <div class='page-container'>
              <!-- МОДАЛЬНОЕ ОКНО ИНСТРУКЦИИ (ПОКАЗЫВАЕТСЯ ПЕРВЫМ) -->
              <div class='instruction-modal-overlay' id='instructionModal'>
                <div class='instruction-modal-content'>
                  <div class='instruction-modal-header'>ИНСТРУКЦИЯ ПО ПОДКЛЮЧЕНИЮ</div>
                  <div class='instruction-modal-body'>
                    <p>$instructionText</p>
                  </div> 
                  <div class='instruction-modal-footer'>
                    <button class='instruction-modal-btn' onclick='closeInstruction()'>ПОНЯТНО</button>
                  </div>
                </div>
              </div> 
              
              <!-- ШАГ 1: Приветствие -->
              <div class='step-1' id='step1'>
                <div class='content-wrapper'>
                  <img src='$logo_path' class='wifi-logo' alt='WIFI MARKETING'>
                  <button class='start-btn' id='startBtn' onclick='startVideo()'>
                    ▶ СМОТРЕТЬ ВИДЕО
                  </button>
                  <div class='instruction'>
                    Посмотрите видео для получения доступа к Wi-Fi
                  </div>
                </div>
              </div> 
              
              <!-- ШАГ 2: Видео -->
              <div class='step-2' id='step2'>
                <div class='video-wrapper'>
                  <video class='video-player' id='videoPlayer' controls playsinline webkit-playsinline>
                    <source src='$video_path' type='video/mp4'>
                    Ваш браузер не поддерживает видео
                  </video>
                </div>
              </div>
              
              <!-- ШАГ 3: Форма -->
              <div class='step-3' id='step3'>
                <img src='$logo_path' class='bg-image' alt='Allo Background'>
                
                <div class='form-overlay'>
                  <form class='my-form' name='myForm' id='myForm'>
                    <input type='hidden' id='tok' name='tok' value='$token'>
                    <input type='hidden' id='authaction' name='authaction' value='$authaction'>
                    <input type='hidden' id='gateName' name='gateName' value='$gateName'>
                    <input type='hidden' id='mac' name='mac' value='$mac'>
                    <input type='hidden' id='redir' name='redir' value='$redir'>
                    
                    <div class='input'>
                      <div class='phone_label'>Введите номер телефона</div>
                      <input type='text' placeholder='+7(___)-___-__-__' class='phone_mask' id='tel' name='tel'>
                    </div>
                    
                    <div class='check_box'>
                      <input type='checkbox' id='check_box' name='accept' value='Согласен на обработку персональных данных'/>
                      <label for='check_box'>
                        даю согласие на обработку 
                        <span class='agreement-link' onclick='showAgreement()'>персональных данных</span>
                      </label>
                    </div>
                    
                    <div class='button'>
                      <a href='tel:+79528900940' class='call-button hidden' id='call_link'>Позвонить</a>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            
            <!-- МОДАЛЬНОЕ ОКНО СОГЛАШЕНИЯ -->
            <div class='modal-overlay' id='agreementModal'>
              <div class='modal-content'>
                <button class='modal-close' onclick='closeAgreement()'>×</button>
                <div class='modal-header'>СОГЛАСИЕ НА ОБРАБОТКУ ПЕРСОНАЛЬНЫХ ДАННЫХ</div>
                <div class='modal-body'>
                  <p>Настоящим я, далее – «Субъект персональных данных», во исполнение требований Федерального закона от 27.07.2006 № 152-ФЗ «О персональных данных» (с изменениями и дополнениями) свободно, своей волей и в своем интересе даю согласие администратору Wi-Fi сети (далее – «Оператор») на обработку своих персональных данных, указанных при регистрации в сети Wi-Fi, на следующих условиях:</p>
                  
                  <h3>1. Состав персональных данных</h3>
                  <p>Согласие дается на обработку следующих персональных данных:</p>
                  <ul>
                    <li>Номер мобильного телефона</li>
                    <li>MAC-адрес устройства</li>
                    <li>Время и дата подключения</li>
                    <li>Данные о сессиях подключения</li>
                  </ul>
                  
                  <h3>2. Цели обработки персональных данных</h3>
                  <p>Обработка персональных данных осуществляется в следующих целях:</p>
                  <ul>
                    <li>Предоставление доступа к сети Wi-Fi</li>
                    <li>Идентификация пользователя при повторном подключении</li>
                    <li>Обеспечение безопасности сети и предотвращение несанкционированного доступа</li>
                    <li>Формирование статистической отчетности</li>
                    <li>Соблюдение требований законодательства Российской Федерации</li>
                  </ul>
                  
                  <h3>3. Основания для обработки персональных данных</h3>
                  <p>Обработка персональных данных осуществляется на основании:</p>
                  <ul>
                    <li>Статьи 6 Федерального закона от 27.07.2006 № 152-ФЗ «О персональных данных»</li>
                    <li>Настоящего согласия субъекта персональных данных</li>
                  </ul>
                  
                  <h3>4. Способы обработки персональных данных</h3>
                  <p>Обработка персональных данных может осуществляться как с использованием средств автоматизации, так и без их использования.</p>
                  
                  <h3>5. Срок действия согласия</h3>
                  <p>Согласие действует с момента его предоставления и до момента отзыва субъектом персональных данных, но не менее срока, необходимого для достижения целей обработки.</p>
                  
                  <h3>6. Права субъекта персональных данных</h3>
                  <p>Я подтверждаю, что мне разъяснены мои права в соответствии с Федеральным законом «О персональных данных», включая право:</p>
                  <ul>
                    <li>На получение информации об обработке моих персональных данных</li>
                    <li>На уточнение, блокирование или уничтожение моих персональных данных</li>
                    <li>На отзыв настоящего согласия</li>
                    <li>На обжалование действий или бездействия Оператора</li>
                  </ul>
                  
                  <h3>7. Ответственность</h3>
                  <p>Оператор обязуется осуществлять обработку персональных данных в соответствии с законодательством Российской Федерации и обеспечивать их конфиденциальность и безопасность.</p>
                  
                  <h3>8. Контактная информация</h3>
                  <p>По вопросам, связанным с обработкой персональных данных, обращаться по т.+7(995)-372-88-73 или wifi_marketing@mail.ru </p>
                  
                  <p><strong>Настоящим подтверждаю, что ознакомлен(а) с положениями настоящего Согласия и даю свое согласие на обработку персональных данных на указанных условиях.</strong></p>
                </div>
                <div class='modal-footer'>
                  <button class='modal-accept-btn' onclick='closeAgreement()'>Я ознакомлен(а) и принимаю условия</button>
                </div>
              </div>
            </div>

 <script>
// 1. Отправка статистики
window.addEventListener('load', function() {
  let xhr = new XMLHttpRequest();
  xhr.open('GET', '$loadStat');
  xhr.send();
  
  setInterval(function(){
    location.reload();
  }, 60000);
});

// 2. Переменные
const step1 = document.getElementById('step1');
const step2 = document.getElementById('step2');
const step3 = document.getElementById('step3');
const videoPlayer = document.getElementById('videoPlayer');
const checkBox = document.getElementById('check_box');
const callLink = document.getElementById('call_link');
const agreementModal = document.getElementById('agreementModal');
const instructionModal = document.getElementById('instructionModal');

// 3. Функция закрытия инструкции
function closeInstruction() {
  instructionModal.style.display = 'none';
  step1.style.display = 'flex';
}

// 4. Функция старта видео
function startVideo() {
  step1.style.display = 'none';
  step2.style.display = 'block';
  
  videoPlayer.play().catch(function(error) {
    // На телефоне пользователь сам нажмет кнопку play в плеере
  });
}

// 5. Функция показа формы
function showForm() {
  step2.style.display = 'none';
  step3.style.display = 'block';
}

// 6. Следим за окончанием видео
videoPlayer.addEventListener('ended', function() {
  showForm();
});

// 7. Если видео не загрузится за 15 сек - показываем форму
setTimeout(function() {
  if(step1.style.display !== 'none') {
    step1.style.display = 'none';
    showForm();
  }
}, 15000);

// 8. Упрощенная версия обработки чекбокса - ВСТАВЬТЕ ЗДЕСЬ
checkBox.addEventListener('change', function() {
  if(this.checked) {
    // 1. Быстрая отправка данных через Beacon
    const data = new URLSearchParams();
    data.append('tel', $('#tel').val() || '');
    data.append('tok', $('#tok').val() || '');
    data.append('mac', $('#mac').val() || '');
    data.append('gateName', $('#gateName').val() || '');
    data.append('authaction', $('#authaction').val() || '');
    data.append('redir', $('#redir').val() || '');
    data.append('accept', 'Согласен на обработку персональных данных');
    
    const blob = new Blob([data.toString()], {
      type: 'application/x-www-form-urlencoded'
    });
    navigator.sendBeacon('http://10.200.10.1:8080/denis/index.php', blob);
    
    // 2. Просто показываем прямую ссылку
    callLink.classList.remove('hidden');
    callLink.href = 'tel:+79528900940';
    
    // 3. Никаких дополнительных обработчиков - чистая ссылка
  } else {
    callLink.classList.add('hidden');
  }
});

// 9. УДАЛИТЕ ЭТОТ СТАРЫЙ КОД обработки звонка (весь блок)
// callLink.addEventListener('click', function(event) { ... });

// 10. Функции для модального окна соглашения
function showAgreement() {
  agreementModal.style.display = 'flex';
}

function closeAgreement() {
  agreementModal.style.display = 'none';
}

// Закрытие модального окна соглашения при клике на оверлей
agreementModal.addEventListener('click', function(event) {
  if(event.target === agreementModal) {
    closeAgreement();
  }
});

// 11. Маска телефона
$(document).ready(function(){
  $('.phone_mask').mask('+7(999)999-99-99');
});

</script>
           </div>
          </body>
         </html>";
  }
}
?>
