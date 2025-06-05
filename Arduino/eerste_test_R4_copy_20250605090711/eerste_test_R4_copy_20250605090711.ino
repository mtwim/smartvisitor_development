#include <WiFiS3.h>
#include <EEPROM.h>
#include <Adafruit_NeoPixel.h>

// ===== LED Ring Configuratie =====
#define LED_PIN 6
#define LED_COUNT 12
Adafruit_NeoPixel strip(LED_COUNT, LED_PIN, NEO_GRB + NEO_KHZ800);

// ===== EEPROM Adressen =====
#define EEPROM_INIT_FLAG 0      
#define EEPROM_SCANNER_ID 1     
#define EEPROM_PROJECT_ID 51    
#define EEPROM_ZONE_ID 55       
#define EEPROM_LOCATION 105     

// ===== WiFi-configuratie =====
const char* ssid     = "Abbamsterdam";
const char* password = "16041975";

// ===== Webhook configuratie =====
const char* host = "smartvisitor.nl";
const char* host_header = "smartvisitor.nl";
const int httpsPort = 443;
const char* path = "/webhooks/tag_webhook.php";
const char* admin_path = "/webhooks/scanner_admin.php";

const char* secret = "y7s3fP9eV4qLm29X";

// ===== SSL Certificaat =====
const char* root_ca = \
"-----BEGIN CERTIFICATE-----\n" \
"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\n" \
"-----END CERTIFICATE-----\n";

#define rfidSerial Serial1

const int BUFFER_SIZE = 128;
byte responseBuffer[BUFFER_SIZE];
int bufferIndex = 0;
char lastTagID[64] = "";
unsigned long lastTagTime = 0;
const unsigned long TAG_COOLDOWN = 3000;

// ===== Variabelen =====
String macAddress = "";
String scannerID = "";
int projectID = 0;
String zoneID = "";
String location = "";
bool isConfigured = false;
unsigned long lastHeartbeat = 0;
unsigned long lastStatusCheck = 0;
bool configurationLoaded = false;

WiFiSSLClient httpsClient;

// ===== EENVOUDIGE LED Status =====
enum LEDStatus {
  LED_OFF,
  LED_WHITE,      // Wit vast - opstarten
  LED_BLUE_BLINK, // Blauw knipperend - WiFi verbinden
  LED_GREEN,      // Groen vast - WiFi OK
  LED_YELLOW_BLINK, // Geel knipperend - registreren
  LED_GREEN_BREATHE, // Groen ademend - klaar
  LED_ORANGE,     // Oranje vast - scannen
  LED_GREEN_FLASH, // Groen flash - succes
  LED_RED_BLINK,  // Rood knipperend - fout
  LED_RAINBOW     // Regenboog - identify
};

LEDStatus currentLEDStatus = LED_OFF;
unsigned long ledLastUpdate = 0;
bool ledBlinkState = false;
unsigned long statusStartTime = 0;
int rainbowHue = 0; // Voor regenboog animatie

void setup() {
  Serial.begin(115200);
  rfidSerial.begin(38400);
  
  // LED strip initialiseren
  strip.begin();
  strip.setBrightness(51); // 20%
  setLEDStatus(LED_OFF);
  
  Serial.println("=== SmartVisitor Scanner System ===");
  Serial.println("LED: Eenvoudige status animaties met werkende regenboog");
  
  // Boot
  setLEDStatus(LED_WHITE);
  delay(2000);
  
  loadConfiguration();
  getMACAddress();
  
  // WiFi verbinden
  setLEDStatus(LED_BLUE_BLINK);
  connectWiFi();
  
  setLEDStatus(LED_GREEN);
  delay(1000);
  
  Serial.println("🔐 SSL certificaat instellen...");
  httpsClient.setCACert(root_ca);
  
  Serial.println("🔧 RFID-module initialiseren...");
  initRFID();
  
  // Scanner registreren
  setLEDStatus(LED_YELLOW_BLINK);
  registerWithServer();
  
  // Status bepalen
  if (isConfigured) {
    setLEDStatus(LED_GREEN_BREATHE);
    Serial.println("✅ Scanner geconfigureerd en klaar!");
  } else {
    setLEDStatus(LED_RED_BLINK);
    Serial.println("⚠️ Scanner niet geconfigureerd - wacht op admin setup");
  }
  
  // Test identify na 5 seconden
  Serial.println("🧪 Test identify functie over 5 seconden...");
  delay(5000);
  testIdentify();
}

void loop() {
  static unsigned long lastScan = 0;
  const unsigned long SCAN_INTERVAL = 50;
  
  // Update LED animaties
  updateLEDs();
  
  // Heartbeat naar server (elke 30 seconden)
  if (millis() - lastHeartbeat > 30000) {
    sendHeartbeat();
    lastHeartbeat = millis();
  }
  
  // Status check - minder frequent en alleen loggen bij belangrijke events
  if ((!configurationLoaded && millis() - lastStatusCheck > 60000) || 
      (millis() - lastStatusCheck > 10000)) { // Elke 10 seconden voor identify
    checkServerStatus();
    lastStatusCheck = millis();
  }
  
  // RFID scanning
  if (isConfigured && millis() - lastScan >= SCAN_INTERVAL) {
    sendCommand("Q");
    processResponse();
    lastScan = millis();
  }
  
  delay(10);
}

// ===== TEST FUNCTIE =====
void testIdentify() {
  Serial.println("🧪 TEST: Forceer identify animatie");
  setLEDStatus(LED_RAINBOW);
}

// ===== EENVOUDIGE LED FUNCTIES =====
void setLEDStatus(LEDStatus newStatus) {
  if (newStatus != currentLEDStatus) {
    currentLEDStatus = newStatus;
    statusStartTime = millis();
    ledLastUpdate = 0;
    ledBlinkState = false;
    rainbowHue = 0; // Reset regenboog
    
    Serial.println("🔄 LED Status: " + getStatusName(newStatus));
    
    // Direct de juiste kleur instellen voor vaste kleuren
    switch (newStatus) {
      case LED_OFF:
        setAllLEDs(0, 0, 0);
        break;
      case LED_WHITE:
        setAllLEDs(20, 20, 20);
        break;
      case LED_GREEN:
        setAllLEDs(0, 30, 0);
        break;
      case LED_ORANGE:
        setAllLEDs(40, 20, 0);
        break;
      default:
        // Animaties worden in updateLEDs() afgehandeld
        break;
    }
  }
}

void updateLEDs() {
  unsigned long currentTime = millis();
  
  switch (currentLEDStatus) {
    case LED_BLUE_BLINK:
      if (currentTime - ledLastUpdate > 500) {
        ledBlinkState = !ledBlinkState;
        ledLastUpdate = currentTime;
        if (ledBlinkState) {
          setAllLEDs(0, 0, 40); // Blauw
        } else {
          setAllLEDs(0, 0, 0);  // Uit
        }
      }
      break;
      
    case LED_YELLOW_BLINK:
      if (currentTime - ledLastUpdate > 300) {
        ledBlinkState = !ledBlinkState;
        ledLastUpdate = currentTime;
        if (ledBlinkState) {
          setAllLEDs(40, 40, 0); // Geel
        } else {
          setAllLEDs(0, 0, 0);   // Uit
        }
      }
      break;
      
    case LED_GREEN_BREATHE:
      {
        // Langzaam ademend groen
        float breath = sin((currentTime - statusStartTime) * 0.003) * 0.5 + 0.5;
        int brightness = 10 + (breath * 20);
        setAllLEDs(0, brightness, 0);
      }
      break;
      
    case LED_GREEN_FLASH:
      // Groen flash voor 1 seconde
      if (currentTime - statusStartTime < 1000) {
        setAllLEDs(0, 50, 0);
      } else {
        setLEDStatus(LED_GREEN_BREATHE);
      }
      break;
      
    case LED_RED_BLINK:
      if (currentTime - ledLastUpdate > 250) {
        ledBlinkState = !ledBlinkState;
        ledLastUpdate = currentTime;
        if (ledBlinkState) {
          setAllLEDs(50, 0, 0); // Rood
        } else {
          setAllLEDs(0, 0, 0);  // Uit
        }
      }
      break;
      
    case LED_RAINBOW:
      // WERKENDE REGENBOOG ANIMATIE
      if (currentTime - statusStartTime < 3000) {
        // Update elke 50ms voor vloeiende animatie
        if (currentTime - ledLastUpdate > 50) {
          rainbowHue = (rainbowHue + 10) % 256; // Verhoog hue
          
          // Converteer hue naar RGB met Adafruit functie
          uint32_t color = strip.gamma32(strip.ColorHSV(rainbowHue * 256, 255, 128)); // 50% brightness
          
          // Zet alle LEDs op dezelfde kleur
          for (int i = 0; i < LED_COUNT; i++) {
            strip.setPixelColor(i, color);
          }
          strip.show();
          
          ledLastUpdate = currentTime;
        }
      } else {
        Serial.println("🌈 Regenboog animatie voltooid");
        setLEDStatus(isConfigured ? LED_GREEN_BREATHE : LED_RED_BLINK);
      }
      break;
      
    default:
      // Statische kleuren hoeven niet geüpdatet te worden
      break;
  }
}

void setAllLEDs(uint8_t r, uint8_t g, uint8_t b) {
  for (int i = 0; i < LED_COUNT; i++) {
    strip.setPixelColor(i, strip.Color(r, g, b));
  }
  strip.show();
}

String getStatusName(LEDStatus status) {
  switch (status) {
    case LED_OFF: return "UIT";
    case LED_WHITE: return "OPSTARTEN";
    case LED_BLUE_BLINK: return "WIFI_VERBINDEN";
    case LED_GREEN: return "WIFI_OK";
    case LED_YELLOW_BLINK: return "REGISTREREN";
    case LED_GREEN_BREATHE: return "KLAAR";
    case LED_ORANGE: return "SCANNEN";
    case LED_GREEN_FLASH: return "SUCCES";
    case LED_RED_BLINK: return "FOUT";
    case LED_RAINBOW: return "IDENTIFICEREN";
    default: return "ONBEKEND";
  }
}

// ===== BESTAANDE FUNCTIES =====
void loadConfiguration() {
  if (EEPROM.read(EEPROM_INIT_FLAG) == 0xAA) {
    char buffer[100];
    
    for (int i = 0; i < 50; i++) {
      buffer[i] = EEPROM.read(EEPROM_SCANNER_ID + i);
      if (buffer[i] == 0) break;
    }
    buffer[49] = 0;
    scannerID = String(buffer);
    
    projectID = (EEPROM.read(EEPROM_PROJECT_ID) << 24) |
                (EEPROM.read(EEPROM_PROJECT_ID + 1) << 16) |
                (EEPROM.read(EEPROM_PROJECT_ID + 2) << 8) |
                EEPROM.read(EEPROM_PROJECT_ID + 3);
    
    for (int i = 0; i < 50; i++) {
      buffer[i] = EEPROM.read(EEPROM_ZONE_ID + i);
      if (buffer[i] == 0) break;
    }
    buffer[49] = 0;
    zoneID = String(buffer);
    
    for (int i = 0; i < 100; i++) {
      buffer[i] = EEPROM.read(EEPROM_LOCATION + i);
      if (buffer[i] == 0) break;
    }
    buffer[99] = 0;
    location = String(buffer);
    
    isConfigured = (scannerID.length() > 0 && projectID > 0);
    
    Serial.println("📋 Configuratie geladen:");
    Serial.println("  Scanner ID: " + scannerID);
    Serial.println("  Project ID: " + String(projectID));
    Serial.println("  Configured: " + String(isConfigured ? "Ja" : "Nee"));
  } else {
    Serial.println("📋 Geen configuratie gevonden");
    isConfigured = false;
  }
}

void saveConfiguration() {
  EEPROM.write(EEPROM_INIT_FLAG, 0xAA);
  
  for (int i = 0; i < 50; i++) {
    if (i < scannerID.length()) {
      EEPROM.write(EEPROM_SCANNER_ID + i, scannerID[i]);
    } else {
      EEPROM.write(EEPROM_SCANNER_ID + i, 0);
      break;
    }
  }
  
  EEPROM.write(EEPROM_PROJECT_ID, (projectID >> 24) & 0xFF);
  EEPROM.write(EEPROM_PROJECT_ID + 1, (projectID >> 16) & 0xFF);
  EEPROM.write(EEPROM_PROJECT_ID + 2, (projectID >> 8) & 0xFF);
  EEPROM.write(EEPROM_PROJECT_ID + 3, projectID & 0xFF);
  
  for (int i = 0; i < 50; i++) {
    if (i < zoneID.length()) {
      EEPROM.write(EEPROM_ZONE_ID + i, zoneID[i]);
    } else {
      EEPROM.write(EEPROM_ZONE_ID + i, 0);
      break;
    }
  }
  
  for (int i = 0; i < 100; i++) {
    if (i < location.length()) {
      EEPROM.write(EEPROM_LOCATION + i, location[i]);
    } else {
      EEPROM.write(EEPROM_LOCATION + i, 0);
      break;
    }
  }
  
  Serial.println("💾 Configuratie opgeslagen");
}

void getMACAddress() {
  byte mac[6];
  WiFi.macAddress(mac);
  macAddress = "";
  for (int i = 0; i < 6; i++) {
    if (i > 0) macAddress += ":";
    if (mac[i] < 16) macAddress += "0";
    macAddress += String(mac[i], HEX);
  }
  macAddress.toUpperCase();
  Serial.println("MAC-adres: " + macAddress);
}

void connectWiFi() {
  Serial.print("Verbinden met WiFi");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println(" ✅ Verbonden!");
}

void initRFID() {
  sendCommand("P");     delay(200);
  sendCommand("N5,01"); delay(200);
  sendCommand("N1,1B"); delay(200);
  sendCommand("A1");    delay(200);
  Serial.println("✅ RFID-module klaar");
}

void registerWithServer() {
  Serial.println("📡 Registreren bij server...");
  
  String payload = "action=register" +
                   String("&mac_address=") + macAddress +
                   String("&current_scanner_id=") + (scannerID.length() > 0 ? scannerID : "NEW") +
                   String("&secret=") + String(secret);

  String response = sendPostRequest(admin_path, payload, 10000);
  
  if (response.length() > 0 && response.indexOf("\"success\":true") > 0) {
    Serial.println("✅ Succesvol geregistreerd");
    
    int scannerIdStart = response.indexOf("\"scanner_id\":\"") + 14;
    int scannerIdEnd = response.indexOf("\"", scannerIdStart);
    if (scannerIdStart > 13 && scannerIdEnd > scannerIdStart) {
      String newScannerID = response.substring(scannerIdStart, scannerIdEnd);
      if (newScannerID != scannerID) {
        scannerID = newScannerID;
        saveConfiguration();
        Serial.println("Scanner ID bijgewerkt: " + scannerID);
      }
    }
  } else {
    Serial.println("⚠️ Registratie gefaald");
    setLEDStatus(LED_RED_BLINK);
  }
}

void sendHeartbeat() {
  String currentStatusStr = isConfigured ? "ready" : "unconfigured";
  
  String payload = "action=heartbeat" +
                   String("&scanner_id=") + scannerID +
                   String("&mac_address=") + macAddress +
                   String("&status=") + currentStatusStr +
                   String("&secret=") + String(secret);

  String response = sendPostRequest(admin_path, payload, 5000);
  
  if (response.length() > 0 && response.indexOf("\"success\":true") > 0) {
    Serial.println("💓 Heartbeat OK");
  } else {
    Serial.println("⚠️ Heartbeat gefaald");
  }
}

void checkServerStatus() {
  if (scannerID.length() == 0) return;
  
  String payload = "action=check_config" +
                   String("&scanner_id=") + scannerID +
                   String("&mac_address=") + macAddress +
                   String("&secret=") + String(secret);

  String response = sendPostRequest(admin_path, payload, 5000);
  
  if (response.length() > 0) {
    // ALLEEN loggen als er iets belangrijks gebeurt
    if (response.indexOf("\"identify\":true") > 0) {
      Serial.println("🌈 IDENTIFY COMMANDO ONTVANGEN!");
      setLEDStatus(LED_RAINBOW);
    }
    
    if (response.indexOf("\"config_updated\":true") > 0) {
      Serial.println("🔄 Configuratie update ontvangen");
      loadConfigurationFromResponse(response);
      configurationLoaded = true;
      setLEDStatus(LED_GREEN_BREATHE);
    }
  }
}

String sendPostRequest(const char* path, String payload, unsigned long timeout) {
  String response = "";
  
  if (httpsClient.connect(host, httpsPort)) {
    httpsClient.println("POST " + String(path) + " HTTP/1.1");
    httpsClient.println("Host: " + String(host_header));
    httpsClient.println("User-Agent: ArduinoUnoR4WiFi/1.0");
    httpsClient.println("Content-Type: application/x-www-form-urlencoded");
    httpsClient.println("Connection: close");
    httpsClient.print("Content-Length: ");
    httpsClient.println(payload.length());
    httpsClient.println();
    httpsClient.println(payload);
    
    unsigned long startTime = millis();
    while (httpsClient.available() == 0) {
      if (millis() - startTime > timeout) {
        httpsClient.stop();
        return "";
      }
      delay(10);
    }
    
    bool inBody = false;
    while (httpsClient.available()) {
      String line = httpsClient.readStringUntil('\n');
      line.trim();
      
      if (line == "") {
        inBody = true;
        continue;
      }
      
      if (inBody) {
        response += line;
      }
    }
    
    httpsClient.stop();
    return response;
  }
  
  return "";
}

void loadConfigurationFromResponse(String response) {
  int projectIdStart = response.indexOf("\"project_id\":") + 13;
  int projectIdEnd = response.indexOf(",", projectIdStart);
  if (projectIdStart > 12 && projectIdEnd > projectIdStart) {
    projectID = response.substring(projectIdStart, projectIdEnd).toInt();
  }
  
  int zoneIdStart = response.indexOf("\"zone_id\":\"") + 11;
  int zoneIdEnd = response.indexOf("\"", zoneIdStart);
  if (zoneIdStart > 10 && zoneIdEnd > zoneIdStart) {
    zoneID = response.substring(zoneIdStart, zoneIdEnd);
  }
  
  int locationStart = response.indexOf("\"location\":\"") + 12;
  int locationEnd = response.indexOf("\"", locationStart);
  if (locationStart > 11 && locationEnd > locationStart) {
    location = response.substring(locationStart, locationEnd);
  }
  
  isConfigured = (scannerID.length() > 0 && projectID > 0);
  saveConfiguration();
  
  Serial.println("🔄 Configuratie bijgewerkt van server");
}

void sendCommand(const char* cmd) {
  while (rfidSerial.available()) rfidSerial.read();
  while (*cmd) rfidSerial.write(*cmd++);
  rfidSerial.write(0x0D);
}

void processResponse() {
  bufferIndex = 0;
  unsigned long startTime = millis();

  while (millis() - startTime < 200) {
    if (rfidSerial.available()) {
      byte inByte = rfidSerial.read();
      if (bufferIndex < BUFFER_SIZE) {
        responseBuffer[bufferIndex++] = inByte;
      }
      startTime = millis();
    }
  }

  if (bufferIndex > 0) {
    char currentTagID[64] = "";
    int tagIndex = 0;
    int startIndex = 0;

    for (int i = 0; i < bufferIndex; i++) {
      if (responseBuffer[i] == 0x0A) {
        startIndex = i + 1;
        break;
      }
    }

    for (int i = startIndex; i < bufferIndex && tagIndex < 63; i++) {
      if (responseBuffer[i] >= 32 && responseBuffer[i] <= 126) {
        currentTagID[tagIndex++] = responseBuffer[i];
      }
    }
    currentTagID[tagIndex] = '\0';

    if (strlen(currentTagID) < 6 || strcmp(currentTagID, "Q") == 0) return;

    bool isDifferentTag = strcmp(currentTagID, lastTagID) != 0;
    bool cooldownExpired = (millis() - lastTagTime) > TAG_COOLDOWN;

    if (isDifferentTag || cooldownExpired) {
      strcpy(lastTagID, currentTagID);
      lastTagTime = millis();
      
      Serial.print("🎯 Tag gedetecteerd: ");
      Serial.println(currentTagID);
      
      setLEDStatus(LED_ORANGE);
      bool success = sendTagToServer(currentTagID);
      
      if (success) {
        setLEDStatus(LED_GREEN_FLASH);
        Serial.println("✅ Tag succesvol verwerkt");
        strcpy(lastTagID, "");
        lastTagTime = 0;
      } else {
        setLEDStatus(LED_RED_BLINK);
        Serial.println("❌ Fout bij verwerken tag");
        delay(2000);
        setLEDStatus(LED_GREEN_BREATHE);
      }
    }
  }
}

bool sendTagToServer(const char* tagID) {
  String payload = "tag_id=" + String(tagID) +
                   "&scanner_id=" + scannerID +
                   "&mac_address=" + macAddress +
                   "&project_id=" + String(projectID) +
                   "&zone_id=" + zoneID +
                   "&secret=" + String(secret);

  String response = sendPostRequest(path, payload, 15000);
  
  if (response.length() > 0) {
    if (response.indexOf("200") > 0 || response.indexOf("✅") >= 0 || response.indexOf("Scan opgeslagen") >= 0) {
      return true;
    }
  }
  
  return false;
}
