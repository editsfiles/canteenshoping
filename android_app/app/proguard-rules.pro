# Add project specific ProGuard rules here.
-keepclassmembers class * extends android.webkit.WebViewClient {
    public void *(android.webkit.WebView, java.lang.String);
}

# Keep data models for Gson serialization
-keep class com.collegecanteen.app.data.model.** { *; }

# Keep Retrofit interfaces
-keep interface com.collegecanteen.app.data.remote.ApiService { *; }

# Gson specific rules
-keep class sun.misc.Unsafe { *; }
-keep class com.google.gson.stream.** { *; }
