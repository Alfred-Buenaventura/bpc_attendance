using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;     // Keep this for Task.Run... wait, no, we are removing it.
using System.Net.Http;          // Added for HTTP requests
using WebSocketSharp;
using WebSocketSharp.Server;
using Newtonsoft.Json;
using libzkfpcsharp;

public class FingerprintService : WebSocketBehavior
{
    // --- SDK and Device Handles (STATIC) ---
    private static IntPtr zkTecoDeviceHandle = IntPtr.Zero;
    private static IntPtr mDBHandle = IntPtr.Zero;
    private static bool sdkInitialized = false;
    private static bool deviceConnected = false;
    private static int imageWidth = 0;
    private static int imageHeight = 0;
    private static byte[] imgBuffer = null;

    // --- State Flags (STATIC) ---
    private static volatile bool isEnrolling = false;
    private static volatile bool isVerifying = false;

    /// <summary>
    /// Static Constructor: Initializes SDK, DB, and Device.
    /// NOW ALSO loads all templates from the PHP web server.
    /// </summary>
    static FingerprintService()
    {
        Console.WriteLine("[Static Constructor] Initializing ZKFinger SDK (zkfp2.Init)...");
        int ret = zkfp2.Init();
        if (ret == 0)
        {
            sdkInitialized = true;
            Console.WriteLine("[Static Constructor] SDK Initialized. Initializing DB Cache (zkfp2.DBInit)...");

            mDBHandle = zkfp2.DBInit();
            if (mDBHandle == IntPtr.Zero)
            {
                Console.WriteLine("[Static Constructor] ERROR: Could not initialize DB Cache.");
                return;
            }
            Console.WriteLine("[Static Constructor] DB Cache Initialized.");

            Console.WriteLine("[Static Constructor] Opening device (zkfp2.OpenDevice)...");
            zkTecoDeviceHandle = zkfp2.OpenDevice(0);
            if (zkTecoDeviceHandle == IntPtr.Zero)
            {
                Console.WriteLine("[Static Constructor] ERROR: Could not open device.");
                return;
            }
            deviceConnected = true;
            Console.WriteLine("[Static Constructor] Device opened successfully.");

            // Get image dimensions
            byte[] paramValue = new byte[4]; int size = 4;
            if (zkfp2.GetParameters(zkTecoDeviceHandle, 1, paramValue, ref size) == 0) zkfp2.ByteArray2Int(paramValue, ref imageWidth);
            size = 4;
            if (zkfp2.GetParameters(zkTecoDeviceHandle, 2, paramValue, ref size) == 0) zkfp2.ByteArray2Int(paramValue, ref imageHeight);

            if (imageWidth > 0 && imageHeight > 0)
            {
                Console.WriteLine($"[Static Constructor] Obtained Dimensions: {imageWidth} x {imageHeight}");
                imgBuffer = new byte[imageWidth * imageHeight];
            }
            else
            {
                Console.WriteLine("[Static Constructor] ERROR: Could not get image dimensions.");
            }

            // --- *** FIX: Replaced Task.Run with new Thread *** ---
            Console.WriteLine("[Static Constructor] Starting background thread to load templates...");
            // We must use a ParameterizedThreadStart to pass 'this' safely
            new Thread(() => LoadTemplatesFromWebServer()).Start();
            // ------------------------------------------------------
        }
        else
        {
            Console.WriteLine($"[Static Constructor] CRITICAL: SDK Init failed! Error: {GetErrorMsg(ret)} (Code: {ret})");
        }
    }

    /// <summary>
    /// NEW METHOD: Fetches all templates from the PHP API and loads them into the SDK cache.
    /// This now runs on its own dedicated thread.
    /// </summary>
    private static async void LoadTemplatesFromWebServer() // Must be static now
    {
        // IMPORTANT: Adjust this URL to match your server path
        string url = "http://127.0.0.1/bpc_attendance/api/get_all_templates.php";

        Console.WriteLine($"[LoadTemplates] Fetching from {url} ...");

        using (HttpClient client = new HttpClient())
        {
            try
            {
                // Using .Result blocks this thread, which is fine since it's a background thread
                HttpResponseMessage response = client.GetAsync(url).Result;
                if (response.IsSuccessStatusCode)
                {
                    string jsonString = response.Content.ReadAsStringAsync().Result;
                    dynamic result = JsonConvert.DeserializeObject(jsonString);

                    if (result.success == true)
                    {
                        int count = 0;
                        int failed = 0;
                        foreach (var user in result.data)
                        {
                            try
                            {
                                int userId = user.id;
                                string templateBase64 = user.fingerprint_template;
                                byte[] template = Convert.FromBase64String(templateBase64);

                                int ret = zkfp2.DBAdd(mDBHandle, userId, template);
                                if (ret == 0)
                                {
                                    count++;
                                }
                                else
                                {
                                    Console.WriteLine($"[LoadTemplates] Failed to add template for user {userId}. Error: {GetErrorMsg(ret)}");
                                    failed++;
                                }
                            }
                            catch (Exception ex)
                            {
                                Console.WriteLine($"[LoadTemplates] Error parsing template for user: {ex.Message}");
                                failed++;
                            }
                        }
                        Console.WriteLine($"[LoadTemplates] SUCCESS: Loaded {count} templates into SDK cache. {failed} failed.");
                    }
                    else
                    {
                        Console.WriteLine($"[LoadTemplates] Web server returned error: {result.message}");
                    }
                }
                else
                {
                    Console.WriteLine($"[LoadTemplates] HTTP Error: {response.StatusCode}. Is the web server running?");
                }
            }
            catch (Exception ex)
            {
                // This catch block is important because HTTP errors can be noisy
                Console.WriteLine($"[LoadTemplates] CRITICAL: Failed to connect to web server.");
                if (ex.InnerException != null)
                {
                    Console.WriteLine($"[LoadTemplates] Error: {ex.InnerException.Message}");
                }
                else
                {
                    Console.WriteLine($"[LoadTemplates] Error: {ex.Message}");
                }
                Console.WriteLine("Please ensure XAMPP/WAMP is running and the URL is correct.");
            }
        }
    }


    public static void ShutdownSDK()
    {
        Console.WriteLine("[ShutdownSDK] Server is stopping. Releasing all SDK resources...");
        isVerifying = false;
        isEnrolling = false;

        if (zkTecoDeviceHandle != IntPtr.Zero)
        {
            int retClose = zkfp2.CloseDevice(zkTecoDeviceHandle);
            Console.WriteLine($"[ShutdownSDK] Device close result: {GetErrorMsg(retClose)}");
            zkTecoDeviceHandle = IntPtr.Zero;
        }

        if (mDBHandle != IntPtr.Zero)
        {
            int retDbFree = zkfp2.DBFree(mDBHandle);
            Console.WriteLine($"[ShutdownSDK] DB Cache free result: {GetErrorMsg(retDbFree)}");
            mDBHandle = IntPtr.Zero;
        }

        if (sdkInitialized)
        {
            int retTerminate = zkfp2.Terminate();
            Console.WriteLine($"[ShutdownSDK] SDK termination result: {GetErrorMsg(retTerminate)}");
            sdkInitialized = false;
        }
        Console.WriteLine("[ShutdownSDK] Cleanup complete.");
    }

    protected override void OnOpen()
    {
        Console.WriteLine("[OnOpen] Web page client connected.");
        Send(JsonConvert.SerializeObject(new
        {
            status = "info",
            message = "Connected to bridge.",
            sdk = sdkInitialized,
            device = deviceConnected
        }));
    }

    protected override void OnClose(CloseEventArgs e)
    {
        Console.WriteLine("[OnClose] Web page client disconnected.");
        if (isVerifying)
        {
            Console.WriteLine("[OnClose] Client was in verification mode. Stopping verification.");
            isVerifying = false;
        }
    }

    /// <summary>
    /// Main message handler. Now handles verification commands.
    /// </summary>
    protected override void OnMessage(MessageEventArgs e)
    {
        Console.WriteLine("[OnMessage] Message received: " + e.Data);
        dynamic msg = null;
        try
        {
            msg = JsonConvert.DeserializeObject(e.Data);
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[OnMessage] Error deserializing JSON: {ex.Message}");
            return;
        }

        string command = msg.command;
        if (string.IsNullOrEmpty(command)) return;

        if (command == "status")
        {
            Send(JsonConvert.SerializeObject(new
            {
                status = "info",
                message = "Status check.",
                sdk = sdkInitialized,
                device = deviceConnected,
                enrolling = isEnrolling,
                verifying = isVerifying
            }));
        }
        else if (command == "enroll_start")
        {
            Console.WriteLine("[OnMessage] 'enroll_start' command received.");
            if (isVerifying)
            {
                Console.WriteLine("[OnMessage] Error: Verification is in progress.");
                Send(JsonConvert.SerializeObject(new { status = "error", message = "Cannot enroll while verification is active." }));
                return;
            }
            if (isEnrolling)
            {
                Console.WriteLine("[OnMessage] Error: Enrollment already in progress.");
                Send(JsonConvert.SerializeObject(new { status = "error", message = "Enrollment is already in progress." }));
                return;
            }
            if (zkTecoDeviceHandle == IntPtr.Zero || mDBHandle == IntPtr.Zero || !deviceConnected)
            {
                Console.WriteLine("[OnMessage] Error: Device or DB Cache not ready.");
                Send(JsonConvert.SerializeObject(new { status = "error", message = "Device/SDK not fully ready." }));
                return;
            }

            // --- *** FIX: Replaced Task.Run with new Thread *** ---
            // Pass 'this' (the service instance) to the thread
            new Thread(() => StartEnrollmentProcess(this)).Start();
            Console.WriteLine("[OnMessage] Handed off enrollment to background thread.");
        }
        else if (command == "verify_start")
        {
            Console.WriteLine("[OnMessage] 'verify_start' command received.");
            if (isEnrolling)
            {
                Console.WriteLine("[OnMessage] Error: Enrollment is in progress.");
                Send(JsonConvert.SerializeObject(new { status = "error", message = "Cannot verify while enrollment is active." }));
                return;
            }
            if (isVerifying)
            {
                Console.WriteLine("[OnMessage] Warning: Verification already in progress.");
                Send(JsonConvert.SerializeObject(new { status = "info", message = "Verification is already active." }));
                return;
            }
            if (zkTecoDeviceHandle == IntPtr.Zero || mDBHandle == IntPtr.Zero || !deviceConnected)
            {
                Console.WriteLine("[OnMessage] Error: Device or DB Cache not ready.");
                Send(JsonConvert.SerializeObject(new { status = "error", message = "Device/SDK not fully ready." }));
                return;
            }

            // --- *** FIX: Replaced Task.Run with new Thread *** ---
            // Pass 'this' (the service instance) to the thread
            new Thread(() => StartVerificationProcess(this)).Start();
            Console.WriteLine("[OnMessage] Handed off verification to background thread.");
        }
        else if (command == "verify_stop")
        {
            Console.WriteLine("[OnMessage] 'verify_stop' command received.");
            if (isVerifying)
            {
                isVerifying = false;
                Console.WriteLine("[OnMessage] Verification stopped by user command.");
                Send(JsonConvert.SerializeObject(new { status = "info", message = "Verification stopped." }));
            }
            else
            {
                Send(JsonConvert.SerializeObject(new { status = "info", message = "Verification was not running." }));
            }
        }
    }

    /// <summary>
    /// Helper method to send a message from a background thread.
    /// We need this because 'Send' belongs to the WebSocketBehavior instance.
    /// </summary>
    private void SendFromThread(string jsonMessage)
    {
        if (this.Context.WebSocket.ReadyState == WebSocketState.Open)
        {
            Send(jsonMessage);
        }
    }

    /// <summary>
    /// Helper method to broadcast a message from a background thread.
    /// We need this because 'Sessions' belongs to the WebSocketBehavior instance.
    /// </summary>
    private void BroadcastFromThread(string jsonMessage)
    {
        Sessions.Broadcast(jsonMessage);
    }


    /// <summary>
    /// Long-running enrollment task.
    /// NOW accepts a 'service' parameter to send messages.
    /// </summary>
    private void StartEnrollmentProcess(FingerprintService service)
    {
        try
        {
            isEnrolling = true;
            Console.WriteLine("[Task-Enroll] Enrollment task started...");

            List<byte[]> capturedTemplates = new List<byte[]>();
            const int requiredScans = 3;
            int currentScan = 1;
            bool enrollAbort = false;

            while (capturedTemplates.Count < requiredScans && !enrollAbort)
            {
                Console.WriteLine($"[Task-Enroll] Starting capture attempt for scan #{currentScan}...");
                service.SendFromThread(JsonConvert.SerializeObject(new { status = "progress", step = currentScan, message = $"Place finger for scan {currentScan} of {requiredScans}..." }));

                byte[] tempTemplate = new byte[2048];
                int tempSize = 2048;
                bool scanOk = false;
                int retryCount = 0;
                const int maxRetriesPerScan = 50;

                while (!scanOk && retryCount < maxRetriesPerScan && isEnrolling)
                {
                    tempSize = 2048;
                    int ret = zkfp2.AcquireFingerprint(zkTecoDeviceHandle, imgBuffer, tempTemplate, ref tempSize);

                    if (ret == 0 && tempSize > 0)
                    {
                        Console.WriteLine($"[Task-Enroll] Capture {retryCount + 1} for scan #{currentScan} successful. Size: {tempSize}");

                        if (capturedTemplates.Count > 0)
                        {
                            int matchScore = zkfp2.DBMatch(mDBHandle, tempTemplate, capturedTemplates[0]);
                            Console.WriteLine($"[Task-Enroll] Comparing scan #{currentScan} to scan #1. Score: {matchScore}");

                            if (matchScore < 50)
                            {
                                Console.WriteLine("[Task-Enroll] Error: Finger mismatch.");
                                service.SendFromThread(JsonConvert.SerializeObject(new { status = "error", message = "Finger mismatch. Please use the same finger." }));
                                enrollAbort = true;
                                break;
                            }
                        }

                        byte[] validTemplate = new byte[tempSize];
                        Array.Copy(tempTemplate, validTemplate, tempSize);
                        capturedTemplates.Add(validTemplate);
                        scanOk = true;

                        service.SendFromThread(JsonConvert.SerializeObject(new { status = "progress", step = currentScan, message = $"Scan {currentScan} of {requiredScans} captured." }));
                    }
                    else if (ret == -8) { /* No finger, keep polling */ }
                    else
                    {
                        Console.WriteLine($"[Task-Enroll] Capture failed. Error: {GetErrorMsg(ret)}");
                        service.SendFromThread(JsonConvert.SerializeObject(new { status = "error", message = $"Scan {currentScan} failed ({GetErrorMsg(ret)})." }));
                        enrollAbort = true;
                        break;
                    }

                    retryCount++;
                    if (!scanOk && !enrollAbort)
                    {
                        Thread.Sleep(200);
                    }
                }

                if (!scanOk && !enrollAbort)
                {
                    Console.WriteLine($"[Task-Enroll] Scan #{currentScan} timed out.");
                    service.SendFromThread(JsonConvert.SerializeObject(new { status = "error", message = $"Scan {currentScan} timed out." }));
                    enrollAbort = true;
                }
                currentScan++;
            }

            if (capturedTemplates.Count == requiredScans && !enrollAbort)
            {
                Console.WriteLine("[Task-Enroll] Merging templates...");
                byte[] mergedTemplate = new byte[2048];
                int mergeSize = 2048;
                int retMerge = zkfp2.DBMerge(mDBHandle, capturedTemplates[0], capturedTemplates[1], capturedTemplates[2], mergedTemplate, ref mergeSize);

                if (retMerge == 0 && mergeSize > 0)
                {
                    string finalTemplateBase64 = Convert.ToBase64String(mergedTemplate, 0, mergeSize);
                    Console.WriteLine("[Task-Enroll] Merge complete. Size: " + mergeSize);
                    service.SendFromThread(JsonConvert.SerializeObject(new { status = "success", template = finalTemplateBase64, message = "Enrollment complete!" }));
                }
                else
                {
                    Console.WriteLine($"[Task-Enroll] Error: Failed to merge templates. {GetErrorMsg(retMerge)}");
                    service.SendFromThread(JsonConvert.SerializeObject(new { status = "error", message = $"Failed to finalize enrollment ({GetErrorMsg(retMerge)})." }));
                }
            }
            else if (!enrollAbort)
            {
                Console.WriteLine("[Task-Enroll] Error: Loop finished unexpectedly.");
                service.SendFromThread(JsonConvert.SerializeObject(new { status = "error", message = "Enrollment incomplete." }));
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[Task-Enroll] CRITICAL ERROR: {ex.Message}");
            Console.WriteLine(ex.StackTrace);
            service.SendFromThread(JsonConvert.SerializeObject(new { status = "error", message = "Internal task error: " + ex.Message }));
        }
        finally
        {
            isEnrolling = false;
            Console.WriteLine("[Task-Enroll] Enrollment task finished. 'isEnrolling' flag reset.");
        }
    }


    /// <summary>
    /// Long-running verification task.
    /// NOW accepts a 'service' parameter to broadcast messages.
    /// </summary>
    private void StartVerificationProcess(FingerprintService service)
    {
        try
        {
            isVerifying = true;
            Console.WriteLine("[Task-Verify] Verification task started...");

            service.BroadcastFromThread(JsonConvert.SerializeObject(new { status = "info", message = "Verification active. Waiting for finger..." }));

            byte[] tempTemplate = new byte[2048];
            int retries = 0;

            while (isVerifying)
            {
                int tempSize = 2048;
                int ret = zkfp2.AcquireFingerprint(zkTecoDeviceHandle, imgBuffer, tempTemplate, ref tempSize);

                if (ret == 0 && tempSize > 0)
                {
                    Console.WriteLine("[Task-Verify] Finger acquired. Identifying...");
                    int userID = 0;
                    int matchScore = 0;

                    int retIdentify = zkfp2.DBIdentify(mDBHandle, tempTemplate, ref userID, ref matchScore);

                    if (retIdentify == 0)
                    {
                        Console.WriteLine($"[Task-Verify] VERIFICATION SUCCESS. UserID: {userID}, Score: {matchScore}");

                        service.BroadcastFromThread(JsonConvert.SerializeObject(new
                        {
                            type = "verification_success",
                            user_id = userID
                        }));

                        retries = 0;
                        Thread.Sleep(2000); // Prevent double-scans
                    }
                    else
                    {
                        Console.WriteLine($"[Task-Verify] VERIFICATION FAILED. No match found (Code: {retIdentify})");
                        service.BroadcastFromThread(JsonConvert.SerializeObject(new
                        {
                            type = "verification_fail",
                            message = "Finger not recognized."
                        }));

                        Thread.Sleep(1500);
                    }
                }
                else if (ret == -8)
                {
                    retries = 0; // Normal state, reset retries
                }
                else
                {
                    retries++;
                    Console.WriteLine($"[Task-Verify] SDK Error (Code: {ret}, Retries: {retries}).");
                    if (retries > 5)
                    {
                        Console.WriteLine("[Task-Verify] Too many consecutive errors. Stopping verification.");
                        service.BroadcastFromThread(JsonConvert.SerializeObject(new { status = "error", message = $"Scanner error ({GetErrorMsg(ret)})." }));
                        isVerifying = false;
                    }
                }

                Thread.Sleep(100);
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[Task-Verify] CRITICAL ERROR: {ex.Message}");
            Console.WriteLine(ex.StackTrace);
            service.BroadcastFromThread(JsonConvert.SerializeObject(new { status = "error", message = "Internal verification task error: " + ex.Message }));
        }
        finally
        {
            isVerifying = false;
            Console.WriteLine("[Task-Verify] Verification task stopped. 'isVerifying' flag reset.");
            service.BroadcastFromThread(JsonConvert.SerializeObject(new { status = "info", message = "Verification stopped." }));
        }
    }


    /// <summary>
    /// Helper function for error codes. (Unchanged)
    /// </summary>
    private static string GetErrorMsg(int errCode)
    {
        switch (errCode)
        {
            case 0: return "OK";
            case -1: return "Failed to init algorithm library";
            case -2: return "Failed to init capture library";
            case -3: return "No device connected";
            case -4: return "Not supported by interface";
            case -5: return "Invalid parameter";
            case -6: return "Failed to start device";
            case -7: return "Invalid handle";
            case -8: return "Failed to capture image (no finger?)";
            case -9: return "Failed to extract template";
            case -10: return "Suspension";
            case -11: return "Insufficient memory";
            case -12: return "Fingerprint is being captured";
            case -17: return "Operation failed";
            case -18: return "Capture cancelled";
            case -20: return "Fingerprint comparison failed";
            case -22: return "Failed to combine templates";
            default: return $"Unknown error code {errCode}";
        }
    }
}


/// <summary>
/// Main Program class. (Unchanged)
/// </summary>
public class Program
{
    public static void Main(string[] args)
    {
        Console.WriteLine("--- ZKTecoBridge Starting ---");
        WebSocketServer wssv = null;
        string url = "ws://127.0.0.1:8080";

        try
        {
            Console.WriteLine($"Creating WebSocketServer on {url} ...");
            wssv = new WebSocketServer(url);

            Console.WriteLine("Adding WebSocket Service ('/')...");
            wssv.AddWebSocketService<FingerprintService>("/");

            Console.WriteLine("Starting WebSocket Server...");
            wssv.Start();

            if (wssv.IsListening)
            {
                Console.WriteLine("======================================================");
                Console.WriteLine(" ZKTeco Fingerprint Bridge Service");
                Console.WriteLine("======================================================");
                Console.WriteLine($" Listening on: {url}");
                Console.WriteLine(" Waiting for web page connection...");
                Console.WriteLine("======================================================");
                Console.WriteLine(" Press [Enter] key to stop the server.");
                Console.ReadLine();
            }
            else
            {
                Console.WriteLine("!!! Error: WebSocket Server failed to start. !!!");
                Console.ReadLine();
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine("!!!!! UNHANDLED EXCEPTION DURING STARTUP !!!!!");
            Console.WriteLine(ex.ToString());
            Console.ReadLine();
        }
        finally
        {
            if (wssv != null && wssv.IsListening)
            {
                Console.WriteLine("Stopping WebSocket server...");
                wssv.Stop();
            }

            // Call static shutdown method
            FingerprintService.ShutdownSDK();

            Console.WriteLine("Server stopped. Press Enter to exit.");
            Console.ReadLine();
        }
    }
}