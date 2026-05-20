#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>
#include <unistd.h>
#include <syslog.h>
#include <uci.h>
#include <curl/curl.h>
#include <jansson.h>
#include <dirent.h>
#include <sys/stat.h>
#include <locale.h>

// Константы
#define DEFAULT_SERVER_URL "http://10.200.10.1:8080/get_conf.php"
#define CONFIG_FILE "/etc/config/auth_handler"
#define ROTATION_FILE "/tmp/media_rotation.json"
#define MEDIA_BASE_PATH "/tmp/www"
#define VIDEO_PATH MEDIA_BASE_PATH "/img/video"
#define BANNERS_PATH MEDIA_BASE_PATH "/img/banners"
#define LOGOS_PATH MEDIA_BASE_PATH "/img/logos"
#define DHCP_LEASES_FILE "/tmp/dhcp.leases"

// Структуры
typedef struct {
    char server_url[256];
    char stat_url[256];
    char tg_stat_url[256];
    char auth_url[256];
    char phone_number[20];
    int timeout;
    int debug;
} Config;

typedef struct {
    char authaction[256];
    char ip[20];
    char gateway[50];
    char token[50];
    char redir[512];
} QueryParams;

typedef struct {
    char *data;
    size_t size;
} CurlResponse;

typedef struct {
    int video_index;
    int banner_index;
    int logo_index;
    int video_count;
    int banner_count;
    int logo_count;
    char **video_files;
    char **banner_files;
    char **logo_files;
} MediaRotation;

// Глобальные переменные
static Config config;
static MediaRotation media = {0};

// Функция для получения MAC-адреса из DHCP leases по IP
int get_mac_from_dhcp_leases(const char *client_ip, char *mac, size_t mac_size) {
    FILE *leases = fopen(DHCP_LEASES_FILE, "r");
    if (!leases) {
        syslog(LOG_ERR, "Cannot open %s", DHCP_LEASES_FILE);
        return -1;
    }
    
    char line[512];
    char leases_mac[20];
    char leases_ip[20];
    char leases_hostname[256];
    char leases_id[256];
    
    while (fgets(line, sizeof(line), leases)) {
        // Формат: expires mac ip hostname id
        // Пример: 1698765432 00:11:22:33:44:55 192.168.1.100 hostname *
        int parsed = sscanf(line, "%*s %s %s %s %s", 
                            leases_mac, leases_ip, leases_hostname, leases_id);
        
        if (parsed >= 2) {
            // Удаляем двоеточия из MAC-адреса
            if (strcmp(leases_ip, client_ip) == 0) {
                char *src = leases_mac;
                char *dst = mac;
                while (*src) {
                    if (*src != ':') {
                        *dst++ = *src;
                    }
                    src++;
                }
                *dst = '\0';
                
                fclose(leases);
                syslog(LOG_DEBUG, "Found MAC %s for IP %s in DHCP leases", mac, client_ip);
                return 0;
            }
        }
    }
    
    fclose(leases);
    syslog(LOG_ERR, "MAC not found for IP %s in DHCP leases", client_ip);
    return -1;
}

// Загрузка конфигурации
int load_config() {
    struct uci_context *ctx = uci_alloc_context();
    struct uci_package *pkg = NULL;
    
    if (!ctx) {
        return -1;
    }
    
    // Значения по умолчанию
    strcpy(config.server_url, DEFAULT_SERVER_URL);
    strcpy(config.stat_url, "http://10.200.10.1:8080/denis/stat.php");
    strcpy(config.tg_stat_url, "http://10.200.10.1:8080/denis/tg_stat.php");
    strcpy(config.auth_url, "http://192.168.21.1:2050/opennds_auth/");
    strcpy(config.phone_number, "+79528900940");
    config.timeout = 5;
    config.debug = 0;
    
    if (uci_load(ctx, "auth_handler", &pkg) != 0) {
        uci_free_context(ctx);
        return 0;
    }
    
    struct uci_element *e;
    uci_foreach_element(&pkg->sections, e) {
        struct uci_section *s = uci_to_section(e);
        
        if (strcmp(s->type, "handler") == 0) {
            const char *value;
            
            if ((value = uci_lookup_option_string(ctx, s, "server_url"))) {
                strncpy(config.server_url, value, sizeof(config.server_url)-1);
            }
            if ((value = uci_lookup_option_string(ctx, s, "stat_url"))) {
                strncpy(config.stat_url, value, sizeof(config.stat_url)-1);
            }
            if ((value = uci_lookup_option_string(ctx, s, "tg_stat_url"))) {
                strncpy(config.tg_stat_url, value, sizeof(config.tg_stat_url)-1);
            }
            if ((value = uci_lookup_option_string(ctx, s, "auth_url"))) {
                strncpy(config.auth_url, value, sizeof(config.auth_url)-1);
            }
            if ((value = uci_lookup_option_string(ctx, s, "phone_number"))) {
                strncpy(config.phone_number, value, sizeof(config.phone_number)-1);
            }
            if ((value = uci_lookup_option_string(ctx, s, "timeout"))) {
                config.timeout = atoi(value);
                if (config.timeout <= 0) config.timeout = 5;
            }
            if ((value = uci_lookup_option_string(ctx, s, "debug"))) {
                config.debug = atoi(value);
            }
        }
    }
    
    uci_unload(ctx, pkg);
    uci_free_context(ctx);
    return 0;
}

// Получение списка файлов в директории
char** get_files_from_dir(const char *path, int *count) {
    DIR *dir;
    struct dirent *entry;
    char **files = NULL;
    int capacity = 0;
    *count = 0;
    
    dir = opendir(path);
    if (!dir) {
        return NULL;
    }
    
    while ((entry = readdir(dir)) != NULL) {
        if (strcmp(entry->d_name, ".") == 0 || strcmp(entry->d_name, "..") == 0)
            continue;
            
        char *ext = strrchr(entry->d_name, '.');
        if (ext) {
            if (strstr(path, "video") && 
                (strcmp(ext, ".mp4") == 0 || strcmp(ext, ".avi") == 0 || 
                 strcmp(ext, ".mov") == 0 || strcmp(ext, ".mkv") == 0)) {
                // Видео файлы
            } else if (strstr(path, "banners") || strstr(path, "logos")) {
                if (strcmp(ext, ".jpg") != 0 && strcmp(ext, ".jpeg") != 0 &&
                    strcmp(ext, ".png") != 0 && strcmp(ext, ".gif") != 0)
                    continue;
            } else {
                continue;
            }
        } else {
            continue;
        }
        
        if (*count >= capacity) {
            capacity = capacity ? capacity * 2 : 16;
            files = realloc(files, capacity * sizeof(char*));
            if (!files) {
                closedir(dir);
                return NULL;
            }
        }
        
        files[*count] = strdup(entry->d_name);
        if (!files[*count]) {
            closedir(dir);
            for (int i = 0; i < *count; i++) free(files[i]);
            free(files);
            return NULL;
        }
        (*count)++;
    }
    
    closedir(dir);
    return files;
}

// Загрузка ротации из JSON файла
int load_rotation() {
    FILE *file = fopen(ROTATION_FILE, "r");
    if (!file) {
        media.video_index = 0;
        media.banner_index = 0;
        media.logo_index = 0;
        return 0;
    }
    
    fseek(file, 0, SEEK_END);
    long fsize = ftell(file);
    fseek(file, 0, SEEK_SET);
    
    char *json_str = malloc(fsize + 1);
    if (!json_str) {
        fclose(file);
        return -1;
    }
    
    fread(json_str, 1, fsize, file);
    json_str[fsize] = 0;
    fclose(file);
    
    json_error_t error;
    json_t *root = json_loads(json_str, 0, &error);
    free(json_str);
    
    if (!root) {
        return -1;
    }
    
    media.video_index = (int)json_integer_value(json_object_get(root, "video_index"));
    media.banner_index = (int)json_integer_value(json_object_get(root, "banner_index"));
    media.logo_index = (int)json_integer_value(json_object_get(root, "logo_index"));
    
    json_decref(root);
    return 0;
}

// Сохранение ротации в JSON файл
int save_rotation() {
    json_t *root = json_object();
    json_object_set_new(root, "video_index", json_integer(media.video_index));
    json_object_set_new(root, "banner_index", json_integer(media.banner_index));
    json_object_set_new(root, "logo_index", json_integer(media.logo_index));
    
    char *json_str = json_dumps(root, JSON_INDENT(2));
    json_decref(root);
    
    if (!json_str) {
        return -1;
    }
    
    FILE *file = fopen(ROTATION_FILE, "w");
    if (!file) {
        free(json_str);
        return -1;
    }
    
    fprintf(file, "%s", json_str);
    fclose(file);
    free(json_str);
    
    return 0;
}

// Инициализация медиафайлов
int init_media_rotation() {
    media.video_files = get_files_from_dir(VIDEO_PATH, &media.video_count);
    media.banner_files = get_files_from_dir(BANNERS_PATH, &media.banner_count);
    media.logo_files = get_files_from_dir(LOGOS_PATH, &media.logo_count);
    
    if (load_rotation() != 0) {
        media.video_index = 0;
        media.banner_index = 0;
        media.logo_index = 0;
    }
    
    if (media.video_count == 0) {
        media.video_files = malloc(sizeof(char*));
        media.video_files[0] = strdup("head_phone.mp4");
        media.video_count = 1;
    }
    
    if (media.banner_count == 0) {
        media.banner_files = malloc(sizeof(char*));
        media.banner_files[0] = strdup("plenka.jpeg");
        media.banner_count = 1;
    }
    
    if (media.logo_count == 0) {
        media.logo_files = malloc(sizeof(char*));
        media.logo_files[0] = strdup("1.jpeg");
        media.logo_count = 1;
    }
    
    return 0;
}

// Получение следующего медиафайла с ротацией
const char* get_next_video() {
    if (media.video_count == 0) return "head_phone.mp4";
    const char *video = media.video_files[media.video_index];
    media.video_index = (media.video_index + 1) % media.video_count;
    return video;
}

const char* get_next_banner() {
    if (media.banner_count == 0) return "plenka.jpeg";
    const char *banner = media.banner_files[media.banner_index];
    media.banner_index = (media.banner_index + 1) % media.banner_count;
    return banner;
}

const char* get_next_logo() {
    if (media.logo_count == 0) return "1.jpeg";
    const char *logo = media.logo_files[media.logo_index];
    media.logo_index = (media.logo_index + 1) % media.logo_count;
    return logo;
}

// Освобождение ресурсов медиа
void free_media_rotation() {
    for (int i = 0; i < media.video_count; i++) free(media.video_files[i]);
    for (int i = 0; i < media.banner_count; i++) free(media.banner_files[i]);
    for (int i = 0; i < media.logo_count; i++) free(media.logo_files[i]);
    
    free(media.video_files);
    free(media.banner_files);
    free(media.logo_files);
}

// Callback для curl
static size_t write_callback(void *contents, size_t size, size_t nmemb, void *userp) {
    size_t realsize = size * nmemb;
    CurlResponse *response = (CurlResponse *)userp;
    
    char *ptr = realloc(response->data, response->size + realsize + 1);
    if (!ptr) return 0;
    
    response->data = ptr;
    memcpy(&(response->data[response->size]), contents, realsize);
    response->size += realsize;
    response->data[response->size] = 0;
    
    return realsize;
}

// Декодирование URL
void url_decode(char *dst, const char *src) {
    char a, b;
    while (*src) {
        if (*src == '%' && (a = src[1]) && (b = src[2]) && 
            isxdigit((unsigned char)a) && isxdigit((unsigned char)b)) {
            if (a >= 'a') a -= 'a' - 'A';
            if (a >= 'A') a -= ('A' - 10); else a -= '0';
            if (b >= 'a') b -= 'a' - 'A';
            if (b >= 'A') b -= ('A' - 10); else b -= '0';
            *dst++ = 16 * a + b;
            src += 3;
        } else if (*src == '+') {
            *dst++ = ' ';
            src++;
        } else {
            *dst++ = *src++;
        }
    }
    *dst = 0;
}

// Парсинг query string (без mac параметра)
void parse_query_string(const char *query, QueryParams *params) {
    if (!query || !*query) return;
    
    char *query_copy = strdup(query);
    char *token = strtok(query_copy, "&");
    
    while (token) {
        char *eq = strchr(token, '=');
        if (eq) {
            *eq = 0;
            char decoded[512];
            url_decode(decoded, eq + 1);
            
            if (strcmp(token, "authaction") == 0) strncpy(params->authaction, decoded, sizeof(params->authaction)-1);
            else if (strcmp(token, "clientip") == 0) strncpy(params->ip, decoded, sizeof(params->ip)-1);
            else if (strcmp(token, "gatewayname") == 0) strncpy(params->gateway, decoded, sizeof(params->gateway)-1);
            else if (strcmp(token, "tok") == 0) strncpy(params->token, decoded, sizeof(params->token)-1);
            else if (strcmp(token, "redir") == 0) strncpy(params->redir, decoded, sizeof(params->redir)-1);
        }
        token = strtok(NULL, "&");
    }
    
    free(query_copy);
}

// Проверка MAC на сервере (использует полученный MAC из DHCP leases)
int check_mac_on_server(const char *mac, char **server_mac) {
    CURL *curl = curl_easy_init();
    if (!curl) return -1;
    
    char url[512];
    snprintf(url, sizeof(url), "%s?mac=%s", config.server_url, mac);
    
    CurlResponse response = {0};
    response.data = malloc(1);
    response.size = 0;
    
    curl_easy_setopt(curl, CURLOPT_URL, url);
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, write_callback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, config.timeout);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
    
    if (curl_easy_perform(curl) != CURLE_OK) {
        free(response.data);
        curl_easy_cleanup(curl);
        return -1;
    }
    
    json_error_t error;
    json_t *root = json_loads(response.data, 0, &error);
    
    if (root && json_is_array(root) && json_array_size(root) > 0) {
        json_t *first = json_array_get(root, 0);
        json_t *mac_json = json_object_get(first, "mac");
        if (mac_json && json_is_string(mac_json)) {
            *server_mac = strdup(json_string_value(mac_json));
        }
    }
    
    if (root) json_decref(root);
    free(response.data);
    curl_easy_cleanup(curl);
    
    return (*server_mac) ? 0 : -1;
}

// HTML для зарегистрированных (с использованием MAC)
void output_reg_page(const QueryParams *params, const char *mac) {
    const char *banner = get_next_banner();
    const char *logo = get_next_logo();
    
    printf("Content-type: text/html; charset=UTF-8\r\n\r\n");
    
    printf("<!DOCTYPE html><html><head>"
           "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">"
           "<meta name='viewport' content='width=device-width, initial-scale=1.0'>"
           "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>"
           "<link rel='stylesheet' href='/css/reg.css'>"
           "<title>Wi-Fi доступ</title></head><body>"
           "<div class='image-container'>"
           "<img src='/img/banners/%s' class='background-image' alt=''>"
           "<div class='telegram-btn-container'>"
           "<a href='https://t.me/Allo_tomsk' onclick='myFunction()' class='telegram-btn'>"
           "<i class='fab fa-telegram'></i> Telegram</a></div></div>"
           "<script>"
           "function myFunction(){"
           "let x=new XMLHttpRequest();"
           "x.open('GET','%s?mac=%s&gateName=%s');x.send();}"
           "</script>"
           "<script>"
           "window.addEventListener('load',function(){"
           "let x=new XMLHttpRequest();"
           "x.open('GET','%s?gateName=%s&pageName=/img/banners/%s&mac=%s');x.send();"
           "});</script>"
           "</body></html>",
           banner,
           config.tg_stat_url, mac, params->gateway,
           config.stat_url, params->gateway, banner, mac);
}

// HTML для регистрации (с использованием MAC)
void output_no_reg_page(const QueryParams *params, const char *mac) {
    const char *video = get_next_video();
    const char *logo = get_next_logo();
    const char *banner = get_next_banner();
    
    printf("Content-type: text/html; charset=UTF-8\r\n\r\n");
    
    printf("<!DOCTYPE html><html><head>"
           "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">"
           "<meta name='viewport' content='width=device-width, initial-scale=1.0'>"
           "<link rel='stylesheet' href='/css/no_reg.css'>"
           "<title>Регистрация Wi-Fi</title></head><body>"
           "<div class='page-container'>"
           
           "<div class='agreement-modal-overlay' id='agreementModal'>"
           "<div class='agreement-modal-content'>"
           "<div class='agreement-modal-header'>ПОЛЬЗОВАТЕЛЬСКОЕ СОГЛАШЕНИЕ</div>"
           "<div class='agreement-modal-body'>"
           "<h3>1. Общие положения</h3>"
           "<p>1.1. Настоящее Пользовательское соглашение регулирует отношения между Пользователем и владельцем Wi-Fi сети.</p>"
           "<p>1.2. Используя доступ к Wi-Fi сети, Пользователь подтверждает свое согласие с условиями настоящего Соглашения.</p>"
           
           "<h3>2. Условия предоставления доступа</h3>"
           "<p>2.1. Доступ к Wi-Fi сети предоставляется бесплатно.</p>"
           "<p>2.2. Пользователь обязуется использовать сеть в законных целях.</p>"
           "<p>2.3. Запрещается использование сети для распространения вредоносного ПО, спама или незаконного контента.</p>"
           
           "<h3>3. Обработка персональных данных</h3>"
           "<p>3.1. Предоставляя свой номер телефона, Пользователь соглашается на обработку своих персональных данных.</p>"
           "<p>3.2. Цель обработки: предоставление доступа к Wi-Fi сети, идентификация Пользователя.</p>"
           "<p>3.3. Обработка персональных данных осуществляется в соответствии с Федеральным законом №152-ФЗ.</p>"
           
           "<h3>4. Ограничение ответственности</h3>"
           "<p>4.1. Владелец сети не несет ответственности за качество интернет-соединения.</p>"
           "<p>4.2. Владелец сети не гарантирует бесперебойную работу Wi-Fi доступа.</p>"
           
           "<h3>5. Заключительные положения</h3>"
           "<p>5.1. Настоящее Соглашение вступает в силу с момента подключения к Wi-Fi сети.</p>"
           "<p>5.2. Владелец сети вправе вносить изменения в настоящее Соглашение.</p>"
           "</div>"
           "<div class='agreement-modal-footer'>"
           "<button class='agreement-modal-btn' onclick='closeAgreement()'>ЗАКРЫТЬ</button>"
           "</div></div></div>"
           
           "<div class='step-1' id='step1'><div class='content-wrapper'>"
           "<img src='/img/logos/%s' class='wifi-logo' alt='WiFi'>"
           "<button class='start-btn' id='startBtn' onclick='startVideo()'>▶ СМОТРЕТЬ ВИДЕО</button>"
           "<div class='instruction'>Посмотрите видео для получения доступа</div></div></div>"
           
           "<div class='step-2' id='step2'><div class='video-wrapper'>"
           "<video class='video-player' id='videoPlayer' controls playsinline webkit-playsinline>"
           "<source src='/img/video/%s' type='video/mp4'></video></div></div>"
           
           "<div class='step-3' id='step3'><img src='/img/banners/%s' class='bg-image' alt=''>"
           "<div class='form-overlay'><form class='my-form'>"
           "<input type='hidden' id='tok' value='%s'>"
           "<input type='hidden' id='authaction' value='%s'>"
           "<input type='hidden' id='gateName' value='%s'>"
           "<input type='hidden' id='mac' value='%s'>"
           "<input type='hidden' id='redir' value='%s'>"
           
           "<div class='input'><div class='phone_label'>Телефон</div>"
           "<input type='text' placeholder='+7(___)-___-__-__' class='phone_mask' id='tel'></div>"
           
           "<div class='check_box_container'>"
           "<div class='check_box_wrapper'>"
           "<input type='checkbox' id='check_box'>"
           "<label for='check_box'>Я соглашаюсь с <span class='agreement-link' onclick='showAgreement()'>Пользовательским соглашением</span> и даю согласие на обработку персональных данных</label>"
           "</div>"
           "</div>"
           
           "<div class='button'><a href='tel:%s' class='call-button hidden' id='call_link'>Позвонить</a></div></form></div></div></div>"
           
           "<script src='/js/jquery.min.js'></script>"
           "<script src='/js/jquery.maskedinput.js'></script>"
           "<script>"
           "var s1=document.getElementById('step1'),s2=document.getElementById('step2'),v=document.getElementById('videoPlayer'),am=document.getElementById('agreementModal');"

           "document.getElementById('startBtn').onclick=function(){"
           "    s1.style.display='none';"
           "    s2.style.display='block';"
           "    v.play();"
           "};"

           "document.querySelector('.agreement-link').onclick=function(){"
           "    am.style.display='flex';"
           "};"

           "document.querySelector('.agreement-modal-btn').onclick=function(){"
           "    am.style.display='none';"
           "};"

           "v.addEventListener('ended',function(){"
           "    s2.style.display='none';"
           "    document.getElementById('step3').style.display='block';"
           "});"

           "setTimeout(function(){"
           "    if(s1.style.display!=='none'){"
           "        s1.style.display='none';"
           "        document.getElementById('step3').style.display='block';"
           "    }"
           "},15000);"

           "document.getElementById('check_box').addEventListener('change',function(){"
           "    document.getElementById('call_link').style.display=this.checked?'block':'none';"
           "});"

           "document.getElementById('call_link').addEventListener('click',function(e){"
           "    e.preventDefault();"
           "    var d=new URLSearchParams();"
           "    d.append('tel',$('#tel').val());"
           "    d.append('tok',$('#tok').val());"
           "    d.append('mac',$('#mac').val());"
           "    d.append('gateName',$('#gateName').val());"
           "    d.append('authaction',$('#authaction').val());"
           "    d.append('redir',$('#redir').val());"
           "    if($('#check_box').is(':checked')){"
           "        d.append('accept','Согласен');"
           "    }"
           "    $.ajax({"
           "        url:'http://10.200.10.1:8080/denis/index.php',"
           "        type:'POST',"
           "        data:d.toString(),"
           "        contentType:'application/x-www-form-urlencoded',"
           "        success:function(){"
           "            window.location.href='tel:%s';"
           "        }"
           "    });"
           "});"

           "$(document).ready(function(){"
           "    $('.phone_mask').mask('+7(999)999-99-99');"
           "});"

           "window.addEventListener('load',function(){"
           "    let x=new XMLHttpRequest();"
           "    x.open('GET','%s?gateName=%s&pageName=/img/logos/%s&mac=%s');"
           "    x.send();"
           "    setInterval(function(){location.reload();},60000);"
           "});"
           "</script></body></html>",
           logo,
           video,
           banner,
           params->token, params->authaction, params->gateway, mac, params->redir,
           config.phone_number,
           config.phone_number,
           config.stat_url, params->gateway, logo, mac);
}

// Главная функция
int main() {
    setlocale(LC_ALL, "C.UTF-8");
    
    openlog("auth-handler", LOG_PID, LOG_DAEMON);
    
    if (load_config() != 0) {
        printf("Content-type: text/plain; charset=UTF-8\r\n\r\nОшибка конфигурации\n");
        return 1;
    }
    
    if (init_media_rotation() != 0) {
        printf("Content-type: text/plain; charset=UTF-8\r\n\r\nОшибка инициализации медиа\n");
        return 1;
    }
    
    curl_global_init(CURL_GLOBAL_ALL);
    
    char *query_string = getenv("QUERY_STRING");
    if (!query_string) {
        printf("Content-type: text/plain; charset=UTF-8\r\n\r\nНет параметров\n");
        curl_global_cleanup();
        save_rotation();
        free_media_rotation();
        closelog();
        return 1;
    }
    
    QueryParams params = {0};
    parse_query_string(query_string, &params);
    
    // Проверяем наличие IP адреса
    if (!params.ip[0]) {
        printf("Content-type: text/plain; charset=UTF-8\r\n\r\nНет IP адреса клиента\n");
        curl_global_cleanup();
        save_rotation();
        free_media_rotation();
        closelog();
        return 1;
    }
    
    // Получаем MAC из DHCP leases по IP
    char client_mac[20] = {0};
    if (get_mac_from_dhcp_leases(params.ip, client_mac, sizeof(client_mac)) != 0) {
        printf("Content-type: text/plain; charset=UTF-8\r\n\r\nНе удалось определить MAC адрес\n");
        curl_global_cleanup();
        save_rotation();
        free_media_rotation();
        closelog();
        return 1;
    }
    
    syslog(LOG_INFO, "Processing request for IP: %s, MAC: %s", params.ip, client_mac);
    
    char *server_mac = NULL;
    int result = check_mac_on_server(client_mac, &server_mac);
    
    if (result == 0 && server_mac && strcmp(server_mac, client_mac) == 0) {
        output_reg_page(&params, client_mac);
        char cmd[256];
        snprintf(cmd, sizeof(cmd), "ndsctl auth \"%s\" >/dev/null 2>&1", client_mac);
        system(cmd);
        syslog(LOG_INFO, "Client %s authenticated via ndsctl", client_mac);
    } else {
        output_no_reg_page(&params, client_mac);
        syslog(LOG_INFO, "Client %s not registered, showing registration page", client_mac);
    }
    
    save_rotation();
    
    if (server_mac) free(server_mac);
    curl_global_cleanup();
    free_media_rotation();
    closelog();
    
    return 0;
}
