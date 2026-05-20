#
# Makefile for auth_handler (OpenWrt)
# Предназначен для использования в среде OpenWrt SDK
#

include $(TOPDIR)/rules.mk

PKG_NAME:=auth_handler
PKG_VERSION:=1.0
PKG_RELEASE:=1

PKG_BUILD_DIR:=$(BUILD_DIR)/$(PKG_NAME)

include $(INCLUDE_DIR)/package.mk

define Package/$(PKG_NAME)
  SECTION:=net
  CATEGORY:=Network
  SUBMENU:=Captive Portal
  TITLE:=WiFi Authentication Handler
  DEPENDS:=+libuci +libcurl +libjansson +libpthread
  PKGARCH:=all
endef

define Package/$(PKG_NAME)/description
  CGI handler for WiFi authentication with media rotation,
  server checks and NDS integration.
endef

define Build/Prepare
	mkdir -p $(PKG_BUILD_DIR)
	$(CP) ./src/* $(PKG_BUILD_DIR)/
endef

define Build/Configure
endef

# Сама компиляция
define Build/Compile
	$(TARGET_CC) $(TARGET_CFLAGS) -std=gnu99 \
		-I$(STAGING_DIR)/usr/include \
		-I$(STAGING_DIR)/include \
		$(PKG_BUILD_DIR)/auth_handler.c \
		-o $(PKG_BUILD_DIR)/auth_handler \
		$(TARGET_LDFLAGS) \
		-luci -lcurl -ljansson -lpthread
endef

# Установка файлов в целевой образ
define Package/$(PKG_NAME)/install
	$(INSTALL_DIR) $(1)/usr/libexec
	$(INSTALL_BIN) $(PKG_BUILD_DIR)/auth_handler $(1)/usr/libexec/
	
	# Пример установки конфигурации (опционально)
	$(INSTALL_DIR) $(1)/etc/config
	$(INSTALL_DATA) ./files/auth_handler.config $(1)/etc/config/auth_handler || true
	
	# Пример установки веб-файлов (если нужно)
	$(INSTALL_DIR) $(1)/tmp/www
endef

# Определение пакета
$(eval $(call BuildPackage,$(PKG_NAME)))
