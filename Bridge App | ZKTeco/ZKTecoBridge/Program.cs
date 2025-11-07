using System;
using System.Collections.Generic; // Needed for List
using System.Threading;
using System.Threading.Tasks;     // Added for Task.Run
using WebSocketSharp;
using WebSocketSharp.Server;
using Newtonsoft.Json;
using libzkfpcsharp;              // ZKTeco SDK Namespace

/// <summary>
/// WebSocket Service that handles all communication with the ZKTeco scanner.
/// This class is now static-aware, meaning the SDK is initialized ONCE
/// and shared by all connecting clients.
/// </summary>
public class FingerprintService : WebSocketBehavior
{
    // --- SDK and Device Handles (STATIC) ---
    // These are shared by all connections, initialized ONCE by the static constructor.
    private static IntPtr zkTecoDeviceHandle = IntPtr.Zero;
    private static IntPtr mDBHandle = IntPtr.Zero; // Handle for SDK's in-memory cache/DB
    private static bool sdkInitialized = false;
    private static bool deviceConnected = false;
    private static int imageWidth = 0;
    private static int imageHeight = 0;
    private static byte[] imgBuffer = null;

    // --- State Flag (STATIC) ---
    // 'isEnrolling' must also be static, as only one enrollment can happen 
    // on the single physical device at a time, regardless of connected clients.
    private static volatile bool isEnrolling = false; // 'volatile' ensures thread-safety

    /// <summary>
    /// Static Constructor. This runs ONCE when the very first client connects.
    /// It's responsible for initializing the SDK and opening the device.
    /// </summary>
    static FingerprintService()
    {
        Console.WriteLine("[Static Constructor] Initializing ZKFinger SDK (zkfp2.Init)...");
        int ret = zkfp2.Init();
        if (ret == 0) // OK
        {
            sdkInitialized = true;
            Console.WriteLine("[Static Constructor] SDK Initialized. Initializing DB Cache (zkfp2.DBInit)...");

            mDBHandle = zkfp2.DBInit();
            if (mDBHandle == IntPtr.Zero)
            {
                Console.WriteLine("[Static Constructor] ERROR: Could not initialize DB Cache (DBInit failed).");
                return; // SDK remains initialized, but DB functions will fail
            }
            Console.WriteLine("[Static Constructor] DB Cache Initialized.");

            Console.WriteLine("[Static Constructor] Opening device (zkfp2.OpenDevice)...");
            zkTecoDeviceHandle = zkfp2.OpenDevice(0);
            if (zkTecoDeviceHandle == IntPtr.Zero)
            {
                Console.WriteLine("[Static Constructor] ERROR: Could not open device. Is it connected?");
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
        }
        else
        {
            Console.WriteLine($"[Static Constructor] CRITICAL: SDK Init failed! Error: {GetErrorMsg(ret)} (Code: {ret})");
        }
    }

    /// <summary>
    /// Called by Program.Main() when the server is shutting down.
    /// Cleans up all static SDK resources.
    /// </summary>
    public static void ShutdownSDK()
    {
        Console.WriteLine("[ShutdownSDK] Server is stopping. Releasing all SDK resources...");
        // Close Device
        if (zkTecoDeviceHandle != IntPtr.Zero)
        {
            Console.WriteLine("[ShutdownSDK] Closing device...");
            int retClose = zkfp2.CloseDevice(zkTecoDeviceHandle);
            Console.WriteLine($"[ShutdownSDK] Device close result: {GetErrorMsg(retClose)} (Code: {retClose})");
            zkTecoDeviceHandle = IntPtr.Zero;
        }

        // Free the DB Handle
        if (mDBHandle != IntPtr.Zero)
        {
            Console.WriteLine("[ShutdownSDK] Freeing DB Cache...");
            int retDbFree = zkfp2.DBFree(mDBHandle);
            Console.WriteLine($"[ShutdownSDK] DB Cache free result: {GetErrorMsg(retDbFree)} (Code: {retDbFree})");
            mDBHandle = IntPtr.Zero;
        }

        // Terminate SDK
        if (sdkInitialized)
        {
            Console.WriteLine("[ShutdownSDK] Terminating SDK...");
            int retTerminate = zkfp2.Terminate();
            Console.WriteLine($"[ShutdownSDK] SDK termination result: {GetErrorMsg(retTerminate)} (Code: {retTerminate})");
            sdkInitialized = false;
        }
        Console.WriteLine("[ShutdownSDK] Cleanup complete.");
    }


    protected override void OnOpen()
    {
        Console.WriteLine("[OnOpen] Web page client connected.");
        // Send status on connect
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
        // NOTE: We NO LONGER shut down the SDK here.
        // The SDK is static and stays running for all other users.
        // It will be shut down by Program.Main when the server stops.
        Console.WriteLine("[OnClose] Web page client disconnected.");
    }

    /// <summary>
    /// Main message handler. This must be FAST.
    /// It just receives a command and (if needed) starts a background task.
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
                enrolling = isEnrolling
            }));
        }
        else if (command == "enroll_start")
        {
            Console.WriteLine("[OnMessage] 'enroll_start' command received.");

            // Check if an enrollment is already running
            if (isEnrolling)
            {
                Console.WriteLine("[OnMessage] Error: Enrollment already in progress.");
                Send(JsonConvert.SerializeObject(new { status = "error", message = "Enrollment is already in progress." }));
                return;
            }

            // Check if device is ready
            if (zkTecoDeviceHandle == IntPtr.Zero || mDBHandle == IntPtr.Zero || imgBuffer == null || !deviceConnected)
            {
                Console.WriteLine("[OnMessage] Error: Device or DB Cache not ready.");
                Send(JsonConvert.SerializeObject(new { status = "error", message = "Device/SDK not fully ready. Try reconnecting scanner." }));
                return;
            }

            // *** THE FIX ***
            // Start the enrollment process on a NEW thread.
            // This lets OnMessage exit immediately, keeping the WebSocket responsive.
            Task.Run(() => StartEnrollmentProcess());
            Console.WriteLine("[OnMessage] Handed off to background task. OnMessage is complete.");
        }
        // You can add a 'enroll_cancel' command here
        // else if (command == "enroll_cancel")
        // {
        //     // Will need a CancellationTokenSource to properly cancel
        //     Console.WriteLine("[OnMessage] Cancel command received (not yet implemented).");
        // }
    }

    /// <summary>
    /// This is the long-running process, now on a background thread.
    /// It can send messages back to the client using the 'Send()' method.
    /// </summary>
    private void StartEnrollmentProcess()
    {
        try
        {
            isEnrolling = true; // Set the busy flag
            Console.WriteLine("[Task] Enrollment task started...");

            // --- ALL YOUR ORIGINAL ENROLLMENT LOGIC IS HERE ---
            List<byte[]> capturedTemplates = new List<byte[]>();
            const int requiredScans = 3;
            int currentScan = 1;
            bool enrollAbort = false;

            while (capturedTemplates.Count < requiredScans && !enrollAbort)
            {
                Console.WriteLine($"[Task] Starting capture attempt for scan #{currentScan}...");
                // Send progress update TO THE CLIENT
                Send(JsonConvert.SerializeObject(new { status = "progress", step = currentScan, message = $"Place finger for scan {currentScan} of {requiredScans}..." }));

                byte[] tempTemplate = new byte[2048];
                int tempSize = 2048;
                bool scanOk = false;
                int retryCount = 0;
                const int maxRetriesPerScan = 50; // Timeout per scan (~10 sec)

                // Loop for a single scan attempt
                while (!scanOk && retryCount < maxRetriesPerScan && isEnrolling) // Check 'isEnrolling' for cancellation
                {
                    tempSize = 2048; // Reset size
                    int ret = zkfp2.AcquireFingerprint(zkTecoDeviceHandle, imgBuffer, tempTemplate, ref tempSize);

                    if (ret == 0 && tempSize > 0) // Successful capture
                    {
                        Console.WriteLine($"[Task] Capture attempt {retryCount + 1} for scan #{currentScan} successful. Size: {tempSize}");

                        if (capturedTemplates.Count > 0)
                        {
                            int matchScore = zkfp2.DBMatch(mDBHandle, tempTemplate, capturedTemplates[0]);
                            Console.WriteLine($"[Task] Comparing scan #{currentScan} to scan #1. Score: {matchScore}");

                            if (matchScore < 50) // Assuming a threshold of 50
                            {
                                Console.WriteLine("[Task] Error: Finger does not match previous scans. Aborting enrollment.");
                                Send(JsonConvert.SerializeObject(new { status = "error", message = "Finger mismatch. Please use the same finger for all scans." }));
                                enrollAbort = true;
                                break;
                            }
                        }

                        byte[] validTemplate = new byte[tempSize];
                        Array.Copy(tempTemplate, validTemplate, tempSize);
                        capturedTemplates.Add(validTemplate);
                        scanOk = true;

                        Send(JsonConvert.SerializeObject(new { status = "progress", step = currentScan, message = $"Scan {currentScan} of {requiredScans} captured." }));
                    }
                    else if (ret == -8) // No finger / bad capture
                    {
                        // Console.WriteLine($"[Task] Retry {retryCount + 1}: No finger/Bad capture (Code: {ret}). Waiting...");
                        // This state is normal, just keep polling
                    }
                    else // Other SDK error
                    {
                        Console.WriteLine($"[Task] Capture attempt failed. Error: {GetErrorMsg(ret)} (Code: {ret})");
                        Send(JsonConvert.SerializeObject(new { status = "error", message = $"Scan {currentScan} failed ({GetErrorMsg(ret)}). Please try again." }));
                        enrollAbort = true;
                        break;
                    }

                    retryCount++;
                    if (!scanOk && !enrollAbort)
                    {
                        Thread.Sleep(200); // Wait between retries
                    }
                } // End inner while loop (single scan attempt)

                if (!scanOk && !enrollAbort)
                {
                    Console.WriteLine($"[Task] Scan #{currentScan} timed out after {maxRetriesPerScan} retries.");
                    Send(JsonConvert.SerializeObject(new { status = "error", message = $"Scan {currentScan} timed out. Please try again." }));
                    enrollAbort = true;
                }

                currentScan++; // Increment for next scan
            } // End outer while loop (3 scans)

            // --- Merge if all scans were successful ---
            if (capturedTemplates.Count == requiredScans && !enrollAbort)
            {
                Console.WriteLine("[Task] All scans successful. Attempting to merge templates...");
                byte[] mergedTemplate = new byte[2048];
                int mergeSize = 2048;
                int retMerge = zkfp2.DBMerge(mDBHandle, capturedTemplates[0], capturedTemplates[1], capturedTemplates[2], mergedTemplate, ref mergeSize);

                if (retMerge == 0 && mergeSize > 0)
                {
                    string finalTemplateBase64 = Convert.ToBase64String(mergedTemplate, 0, mergeSize);
                    Console.WriteLine("[Task] Templates merged successfully. Final Size: " + mergeSize);
                    Send(JsonConvert.SerializeObject(new { status = "success", template = finalTemplateBase64, message = "Enrollment complete!" }));
                }
                else
                {
                    Console.WriteLine($"[Task] Error: Failed to merge templates. Code: {GetErrorMsg(retMerge)} ({retMerge})");
                    Send(JsonConvert.SerializeObject(new { status = "error", message = $"Failed to finalize enrollment ({GetErrorMsg(retMerge)}). Please try again." }));
                }
            }
            else if (!enrollAbort)
            {
                Console.WriteLine("[Task] Error: Loop finished unexpectedly without enough templates.");
                Send(JsonConvert.SerializeObject(new { status = "error", message = "Enrollment process incomplete. Please try again." }));
            }
            // If enrollAbort was true, error message was already sent.
        }
        catch (AccessViolationException avex)
        {
            Console.WriteLine($"[Task] CRITICAL ACCESS VIOLATION: {avex.Message}");
            Console.WriteLine("This often means the SDK DLL is the wrong version (32/64 bit) or the device was unplugged.");
            Send(JsonConvert.SerializeObject(new { status = "error", message = "Critical SDK Error (AccessViolation). Please restart bridge." }));
            // This error is often fatal, the bridge app may need to be restarted.
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[Task] CRITICAL ERROR in enrollment task: {ex.Message}");
            Console.WriteLine(ex.StackTrace);
            Send(JsonConvert.SerializeObject(new { status = "error", message = "Internal task error: " + ex.Message }));
        }
        finally
        {
            // *** MOST IMPORTANT PART ***
            // Ensure the flag is cleared, even if it fails,
            // so the user can try again.
            isEnrolling = false;
            Console.WriteLine("[Task] Enrollment task finished (or aborted). 'isEnrolling' flag reset.");
        }
    }


    /// <summary>
    /// Helper function to get a human-readable error message from the SDK's error code.
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
} // End FingerprintService class


/// <summary>
/// Main Program class. Starts the WebSocket server.
/// </summary>
public class Program
{
    public static void Main(string[] args)
    {
        Console.WriteLine("--- ZKTecoBridge Starting ---");
        WebSocketServer wssv = null;
        string url = "ws://127.0.0.1:8080"; // Listen on localhost only

        try
        {
            Console.WriteLine($"Creating WebSocketServer on {url} ...");
            wssv = new WebSocketServer(url);

            // Using AddWebSocketService with a lambda initializes the static constructor
            Console.WriteLine("Adding WebSocket Service ('/')...");
            wssv.AddWebSocketService<FingerprintService>("/");

            Console.WriteLine("Starting WebSocket Server...");
            wssv.Start();

            if (wssv.IsListening)
            {
                Console.WriteLine("======================================================");
                Console.WriteLine(" ZKTeco Fingerprint Bridge Service (Using zkfp2 Class)");
                Console.WriteLine("======================================================");
                Console.WriteLine($" Listening on: {url}");
                Console.WriteLine(" Waiting for web page connection...");
                Console.WriteLine(" Ensure SDK DLLs are in the same folder as this .exe");
                Console.WriteLine("======================================================");
                Console.WriteLine(" Press [Enter] key to stop the server.");
                Console.ReadLine(); // Wait for Enter
            }
            else
            {
                Console.WriteLine("!!! Error: WebSocket Server failed to start. !!!");
                Console.WriteLine("Press Enter to exit.");
                Console.ReadLine(); // Pause on error
            }

        }
        catch (Exception ex)
        {
            Console.WriteLine("!!!!! UNHANDLED EXCEPTION DURING STARTUP !!!!!");
            Console.WriteLine(ex.ToString());
            Console.WriteLine("Press Enter to exit.");
            Console.ReadLine(); // Pause on error
        }
        finally
        {
            if (wssv != null && wssv.IsListening)
            {
                Console.WriteLine("Stopping WebSocket server...");
                wssv.Stop();
            }

            // *** NEW CLEANUP STEP ***
            // Call the static shutdown method to release the SDK resources
            FingerprintService.ShutdownSDK();

            Console.WriteLine("Server stopped. Press Enter to exit.");
            Console.ReadLine(); // Add a final pause
        }
    }
}