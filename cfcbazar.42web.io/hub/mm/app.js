// app.js

const log = msg => {
  const c = document.getElementById('console');
  c.textContent += '\n' + msg;
  c.scrollTop = c.scrollHeight;
};

let ready = false;

// 1) Wait for Emscripten runtime to initialize
Module['onRuntimeInitialized'] = () => {
  log('✅ WASM module initialized.');
  document.getElementById('runBtn').disabled = false;
  ready = true;
};

// 2) Hook up the button click
document.getElementById('runBtn').addEventListener('click', () => {
  log('🔍 Starting Lyra2 hash…');

  if (!ready) {
    log('❌ Module not ready');
    return;
  }

  try {
    // 76-byte test vector
    const inputBytes = new Uint8Array([
      0x03,0x05,0xA0,0xDB,0xD6,0xBF,0x05,0xCF,
      0x16,0xE5,0x03,0xF3,0xA6,0x6F,0x78,0x00,
      0x7C,0xBF,0x34,0x14,0x43,0x32,0xEC,0xBF,
      0xC2,0x2E,0xD9,0x5C,0x87,0x00,0x38,0x3B,
      0x30,0x9A,0xCE,0x19,0x23,0xA0,0x96,0x4B,
      0x00,0x00,0x00,0x08,0xBA,0x93,0x9A,0x62,
      0x72,0x4C,0x0D,0x75,0x81,0xFC,0xE5,0x76,
      0x1E,0x9D,0x8A,0x0E,0x6A,0x1C,0x3F,0x92,
      0x4F,0xDD,0x84,0x93,0xD1,0x11,0x56,0x49,
      0xC0,0x5E,0xB6,0x01
    ]);
    const len = inputBytes.length;

    // Create Lyra2 context
    const ctxPtr = Module._LYRA2_create();
    if (!ctxPtr) throw new Error('LYRA2_create failed');

    // Allocate input and output
    const inPtr  = Module._malloc(len);
    const outPtr = Module._malloc(32);

    // Copy input: use global HEAPU8, not Module.HEAPU8
    HEAPU8.set(inputBytes, inPtr);

    // Run the hash: LYRA2(ctx, K, kLen, pwd, pwdlen, tcost)
    Module._LYRA2(ctxPtr, outPtr, 32, inPtr, len, 4);

    // Read back the 32-byte hash from WASM
    const hashBytes = new Uint8Array(HEAPU8.buffer, outPtr, 32);
    const hashHex = Array.from(hashBytes)
                      .map(b => b.toString(16).padStart(2,'0'))
                      .join('');
    log('✅ Hash result: ' + hashHex);

    // Free memory
    Module._free(inPtr);
    Module._free(outPtr);
    Module._LYRA2_destroy(ctxPtr);

  } catch (e) {
    log('❌ Error during hashing: ' + e);
  }
});

