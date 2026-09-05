# 📱 College Canteen Android App

A native Android application built with high-performance WebView, persistent session management, and native UPI payment deep-linking (Google Pay, PhonePe, Paytm).

---

## 🌟 Key Features

- **⚡ Native UPI Payment Deep-Linking**: When students click "Pay Now", the app intercepts `upi://` links and automatically opens their installed UPI app (**Google Pay**, **PhonePe**, **Paytm**, or **BHIM**) to complete payment.
- **🍪 Persistent Sessions**: Keeps students logged in across app restarts using synchronized cookie management.
- **🔄 Pull-to-Refresh**: Seamless pull-down-to-reload using Android's native `SwipeRefreshLayout`.
- **📸 Image Upload Support**: Allows admins and users to take photos with the camera or upload food items from their gallery.
- **📶 Offline Screen**: Clean reconnection screen with a "Retry" button if network connectivity is lost.
- **🛡️ Android 14+ Ready**: Fully compatible with Android 7.0 (API 24) all the way up to Android 15 (API 35).

---

## 🚀 How to Open in Android Studio & Build APK

You already have **Android Studio** installed on your computer at `C:\Program Files\Android\Android Studio`.

### Step 1: Open the Project
1. Open **Android Studio**.
2. Click **Open** (or **File → Open...**).
3. Navigate to:
   ```
   c:\xampp\htdocs\Canteenshoping\android_app
   ```
4. Click **OK**. Android Studio will automatically configure and sync the Gradle dependencies.

---

### Step 2: Build the APK (for sharing with students)
1. In Android Studio, go to the top menu:  
   **Build → Build Bundle(s) / APK(s) → Build APK(s)**
2. Wait a few seconds for the build to complete.
3. A notification will appear at the bottom-right corner:  
   `APK(s) generated successfully for 1 module`
4. Click **locate** to find the built `.apk` file (`app-debug.apk`).
5. **You can now send this `.apk` file to any student via WhatsApp, Telegram, or Google Drive!** They can install and run it on their Android phone.

---

### Step 3: Run directly on your Android Phone
1. Enable **Developer Options** and **USB Debugging** on your Android phone.
2. Connect your phone to your PC using a USB cable.
3. In Android Studio, select your phone in the device dropdown at the top.
4. Click the green **Run ▶** button (or press `Shift + F10`).
5. The Canteen app will be installed and launched directly on your phone!

---

## ⚙️ Configuration (Live vs Localhost)

To change the website URL loaded by the app, open:  
[`android_app/app/src/main/java/com/collegecanteen/app/MainActivity.java`](app/src/main/java/com/collegecanteen/app/MainActivity.java)

Line 33:
```java
// Production Live URL (Default):
public static final String APP_URL = "https://canteenshoping.onrender.com";

// OR for testing with local XAMPP on an Android Emulator:
// public static final String APP_URL = "http://10.0.2.2/Canteenshoping";

// OR for testing with your PC's Wi-Fi IP on a real phone:
// public static final String APP_URL = "http://192.168.1.XX/Canteenshoping";
```
