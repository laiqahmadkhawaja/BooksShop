package io.darearqamgrw.mobile.app

import android.Manifest
import android.annotation.SuppressLint
import android.app.Activity
import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.print.PrintAttributes
import android.print.PrintManager
import android.provider.MediaStore
import android.util.Base64
import android.util.Log
import android.view.ViewGroup
import android.webkit.ConsoleMessage
import android.webkit.JavascriptInterface
import android.webkit.JsPromptResult
import android.webkit.JsResult
import android.webkit.PermissionRequest
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.EditText
import android.widget.FrameLayout
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import java.io.File
import java.io.FileOutputStream

class MainActivity : AppCompatActivity() {

    private lateinit var web: WebView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private var fileCallback: ValueCallback<Array<Uri>>? = null
    private var pendingPermissionRequest: PermissionRequest? = null

    private val startUrl = "https://mobile.darearqamgrw.io/Books/"
    private val allowedHost = "mobile.darearqamgrw.io"
    private val keepInsideApp = false

    private val chooser = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val data = result.data
            val clipData = data?.clipData
            var uris: Array<Uri>? = null
            
            if (clipData != null) {
                uris = Array(clipData.itemCount) { i -> clipData.getItemAt(i).uri }
            } else if (data?.data != null) {
                uris = arrayOf(data.data!!)
            }
            fileCallback?.onReceiveValue(uris)
        } else {
            fileCallback?.onReceiveValue(null)
        }
        fileCallback = null
    }

    private val cameraPermissionLauncher = registerForActivityResult(ActivityResultContracts.RequestPermission()) { isGranted ->
        if (isGranted) {
            pendingPermissionRequest?.grant(arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))
        } else {
            pendingPermissionRequest?.deny()
            Toast.makeText(this, "Camera permission is required for barcode scanning", Toast.LENGTH_SHORT).show()
        }
        pendingPermissionRequest = null
    }

    private fun isInternal(host: String?): Boolean {
        if (host == null) return false
        return host == allowedHost || host.endsWith("." + allowedHost)
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        val rootLayout = FrameLayout(this)
        rootLayout.setBackgroundColor(Color.parseColor("#142850"))

        swipeRefresh = SwipeRefreshLayout(this)
        web = WebView(this)
        swipeRefresh.addView(web)
        rootLayout.addView(swipeRefresh, FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT)

        setContentView(rootLayout)

        // Prevent SwipeRefresh from stealing click/touch events when scrolling or interacting
        swipeRefresh.setOnChildScrollUpCallback { _, _ ->
            web.scrollY > 0
        }

        web.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            allowFileAccess = true
            allowContentAccess = true
            allowFileAccessFromFileURLs = true
            allowUniversalAccessFromFileURLs = true
            javaScriptCanOpenWindowsAutomatically = true
            setSupportMultipleWindows(false)
            mixedContentMode = WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE
            
            useWideViewPort = true
            loadWithOverviewMode = true
            mediaPlaybackRequiresUserGesture = false
            cacheMode = WebSettings.LOAD_DEFAULT
        }

        web.addJavascriptInterface(WebAppInterface(), "AndroidDownloader")
        web.addJavascriptInterface(AndroidPrintShareInterface(), "AndroidPrinter")
        web.addJavascriptInterface(AndroidPrintShareInterface(), "AndroidShare")

        swipeRefresh.setOnRefreshListener { web.reload() }

        web.setDownloadListener { url, _, _, _, _ ->
            try {
                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
            } catch (e: Exception) {}
        }

        web.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val uri = request.url
                val scheme = uri.scheme?.lowercase() ?: ""
                
                // Keep javascript:, data:, blob:, about: inside WebView
                if (scheme == "javascript" || scheme == "data" || scheme == "blob" || scheme == "about") {
                    return false
                }
                
                val host = uri.host
                // If relative path or hash link (#modal, etc.), keep inside WebView
                if (host.isNullOrEmpty()) {
                    return false
                }

                if (scheme == "http" || scheme == "https") {
                    if (keepInsideApp || isInternal(host)) {
                        return false
                    }
                }
                
                try {
                    startActivity(Intent(Intent.ACTION_VIEW, uri))
                } catch (e: Exception) {}
                return true
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                swipeRefresh.isRefreshing = false
                
                // Inject print/share interceptor and modal fix
                web.evaluateJavascript("""
                    (function() {
                        // Override window.print() to open Android native Share Sheet for printing/sharing
                        window.print = function() {
                            if (window.AndroidPrinter && typeof window.AndroidPrinter.printOrShare === 'function') {
                                window.AndroidPrinter.printOrShare(window.location.href, document.title || 'Document');
                            } else if (window.AndroidShare && typeof window.AndroidShare.openShareDialog === 'function') {
                                window.AndroidShare.openShareDialog(window.location.href, document.title || 'Document', "");
                            } else if (navigator.share) {
                                navigator.share({ title: document.title, url: window.location.href }).catch(function(){});
                            }
                        };

                        // Reparent modals to body so they are not trapped inside stacking contexts
                        function reparentModals() {
                            document.querySelectorAll('.modal').forEach(function(m) {
                                if (m.parentElement && m.parentElement !== document.body) {
                                    document.body.appendChild(m);
                                }
                            });
                        }
                        reparentModals();
                        document.addEventListener('show.bs.modal', function(e) {
                            if (e.target && e.target.parentElement !== document.body) {
                                document.body.appendChild(e.target);
                            }
                        });

                        if (window.hasDownloaderInstalled) return;
                        window.hasDownloaderInstalled = true;
                        document.addEventListener('click', function(e) {
                            var target = e.target.closest('a');
                            if (target && target.hasAttribute('download') && target.href.startsWith('blob:')) {
                                e.preventDefault();
                                fetch(target.href)
                                    .then(r => r.blob())
                                    .then(blob => {
                                        var reader = new FileReader();
                                        reader.onload = function() {
                                            var base64 = reader.result.split(',')[1];
                                            var filename = target.getAttribute('download') || 'download';
                                            if (window.AndroidDownloader) {
                                                AndroidDownloader.downloadBase64(filename, base64);
                                            }
                                        };
                                        reader.readAsDataURL(blob);
                                    });
                            }
                        });
                    })();
                """, null)
            }

            override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
                if (request.isForMainFrame) {
                    view.loadUrl("file:///android_asset/offline.html")
                }
            }
        }

        web.webChromeClient = object : WebChromeClient() {
            // Handle Camera & Media Permissions for Barcode Scanning
            override fun onPermissionRequest(request: PermissionRequest) {
                runOnUiThread {
                    val resources = request.resources
                    if (resources.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE)) {
                        if (ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                            request.grant(arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))
                        } else {
                            pendingPermissionRequest = request
                            cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
                        }
                    } else {
                        request.grant(resources)
                    }
                }
            }

            override fun onShowFileChooser(
                webView: WebView,
                filePathCallback: ValueCallback<Array<Uri>>,
                fileChooserParams: FileChooserParams
            ): Boolean {
                fileCallback?.onReceiveValue(null)
                fileCallback = filePathCallback

                val intent = Intent(Intent.ACTION_OPEN_DOCUMENT)
                intent.addCategory(Intent.CATEGORY_OPENABLE)
                intent.type = "*/*"
                
                if (fileChooserParams.mode == FileChooserParams.MODE_OPEN_MULTIPLE) {
                    intent.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true)
                }
                
                intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_WRITE_URI_PERMISSION)

                return try {
                    chooser.launch(intent)
                    true
                } catch (e: Exception) {
                    fileCallback = null
                    false
                }
            }

            // JavaScript Dialogs & Console Logging
            override fun onConsoleMessage(consoleMessage: ConsoleMessage): Boolean {
                Log.d("WebViewConsole", "${consoleMessage.message()} -- From line ${consoleMessage.lineNumber()} of ${consoleMessage.sourceId()}")
                return true
            }

            override fun onJsAlert(view: WebView?, url: String?, message: String?, result: JsResult?): Boolean {
                AlertDialog.Builder(this@MainActivity)
                    .setTitle("Alert")
                    .setMessage(message ?: "")
                    .setPositiveButton(android.R.string.ok) { _, _ -> result?.confirm() }
                    .setOnCancelListener { result?.cancel() }
                    .show()
                return true
            }

            override fun onJsConfirm(view: WebView?, url: String?, message: String?, result: JsResult?): Boolean {
                AlertDialog.Builder(this@MainActivity)
                    .setTitle("Confirmation")
                    .setMessage(message ?: "")
                    .setPositiveButton(android.R.string.ok) { _, _ -> result?.confirm() }
                    .setNegativeButton(android.R.string.cancel) { _, _ -> result?.cancel() }
                    .setOnCancelListener { result?.cancel() }
                    .show()
                return true
            }

            override fun onJsPrompt(view: WebView?, url: String?, message: String?, defaultValue: String?, result: JsPromptResult?): Boolean {
                val input = EditText(this@MainActivity)
                input.setText(defaultValue ?: "")
                AlertDialog.Builder(this@MainActivity)
                    .setTitle("Prompt")
                    .setMessage(message ?: "")
                    .setView(input)
                    .setPositiveButton(android.R.string.ok) { _, _ -> result?.confirm(input.text.toString()) }
                    .setNegativeButton(android.R.string.cancel) { _, _ -> result?.cancel() }
                    .setOnCancelListener { result?.cancel() }
                    .show()
                return true
            }
        }

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (web.canGoBack()) web.goBack() else finish()
            }
        })

        if (savedInstanceState == null) {
            web.loadUrl(startUrl)
        } else {
            web.restoreState(savedInstanceState)
        }
    }

    inner class AndroidPrintShareInterface {
        @JavascriptInterface
        fun openShareDialog() {
            openShareDialog(null, null, "")
        }

        @JavascriptInterface
        fun openShareDialog(url: String?) {
            openShareDialog(url, null, "")
        }

        @JavascriptInterface
        fun openShareDialog(url: String?, title: String?) {
            openShareDialog(url, title, "")
        }

        @JavascriptInterface
        fun openShareDialog(url: String?, title: String?, text: String?) {
            runOnUiThread {
                try {
                    val shareUrl = if (!url.isNullOrBlank()) url else (web.url ?: "")
                    val shareTitle = if (!title.isNullOrBlank()) title else (web.title ?: "Document")
                    val shareBody = if (!text.isNullOrBlank()) "$shareTitle\n$text\n$shareUrl" else "$shareTitle\n$shareUrl"

                    val sendIntent = Intent().apply {
                        action = Intent.ACTION_SEND
                        type = "text/plain"
                        putExtra(Intent.EXTRA_SUBJECT, shareTitle)
                        putExtra(Intent.EXTRA_TEXT, shareBody)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK
                    }
                    val chooser = Intent.createChooser(sendIntent, "Share / Print: $shareTitle")
                    chooser.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                    startActivity(chooser)
                } catch (e: Exception) {
                    e.printStackTrace()
                    Toast.makeText(this@MainActivity, "Unable to open share sheet", Toast.LENGTH_SHORT).show()
                }
            }
        }

        @JavascriptInterface
        fun printDocument() {
            // User requested opening Android share sheet for printing in APK
            openShareDialog(web.url, web.title, "")
        }

        @JavascriptInterface
        fun printOrShare() {
            openShareDialog(web.url, web.title, "")
        }

        @JavascriptInterface
        fun printOrShare(url: String?) {
            openShareDialog(url, web.title, "")
        }

        @JavascriptInterface
        fun printOrShare(url: String?, title: String?) {
            openShareDialog(url, title, "")
        }
    }

    inner class WebAppInterface {
        @JavascriptInterface
        fun downloadBase64(filename: String, base64Data: String) {
            try {
                val decodedBytes = Base64.decode(base64Data, Base64.DEFAULT)
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    val contentValues = ContentValues().apply {
                        put(MediaStore.MediaColumns.DISPLAY_NAME, filename)
                        put(MediaStore.MediaColumns.MIME_TYPE, "application/octet-stream")
                        put(MediaStore.MediaColumns.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS)
                    }
                    val uri = contentResolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, contentValues)
                    if (uri != null) {
                        val outputStream = contentResolver.openOutputStream(uri)
                        outputStream?.write(decodedBytes)
                        outputStream?.close()
                        runOnUiThread {
                            Toast.makeText(this@MainActivity, "Saved to Downloads: $filename", Toast.LENGTH_LONG).show()
                        }
                    }
                } else {
                    val downloadsDir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS)
                    val file = File(downloadsDir, filename)
                    val fos = FileOutputStream(file)
                    fos.write(decodedBytes)
                    fos.close()
                    runOnUiThread {
                        Toast.makeText(this@MainActivity, "Saved to Downloads: $filename", Toast.LENGTH_LONG).show()
                    }
                }
            } catch (e: Exception) {
                e.printStackTrace()
                runOnUiThread {
                    Toast.makeText(this@MainActivity, "Download failed", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        web.saveState(outState)
    }

    override fun onDestroy() {
        web.destroy()
        super.onDestroy()
    }
}
