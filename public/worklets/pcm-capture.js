// AudioWorklet: captures the mic, downsamples to 16 kHz mono PCM16 (what the
// HealthTalk relay expects) and posts 40 ms frames to the main thread.
//
// Lives in /public because worklets are loaded by URL, not bundled.

const TARGET_RATE = 16000;
const FRAME_SAMPLES = 640; // 40 ms at 16 kHz

class PcmCaptureProcessor extends AudioWorkletProcessor {
  constructor() {
    super();
    // `sampleRate` is the AudioContext's rate (usually 48000 or 44100).
    this.ratio = sampleRate / TARGET_RATE;
    this.phase = 0;
    this.sum = 0;
    this.count = 0;
    this.frame = new Int16Array(FRAME_SAMPLES);
    this.frameIndex = 0;
  }

  process(inputs) {
    const channel = inputs[0] && inputs[0][0];
    if (!channel) return true;

    // Box-filter decimation: each output sample is the average of the input
    // samples in its window. Handles non-integer ratios (44.1k -> 16k).
    for (let i = 0; i < channel.length; i++) {
      this.sum += channel[i];
      this.count++;
      this.phase += 1;
      if (this.phase >= this.ratio) {
        this.phase -= this.ratio;
        const s = Math.max(-1, Math.min(1, this.sum / this.count));
        this.frame[this.frameIndex++] = s < 0 ? s * 0x8000 : s * 0x7fff;
        this.sum = 0;
        this.count = 0;

        if (this.frameIndex === FRAME_SAMPLES) {
          this.port.postMessage(this.frame.buffer, [this.frame.buffer]);
          this.frame = new Int16Array(FRAME_SAMPLES);
          this.frameIndex = 0;
        }
      }
    }
    return true;
  }
}

registerProcessor("pcm-capture", PcmCaptureProcessor);
