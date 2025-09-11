import { ref, onUnmounted } from 'vue';

export function useAudioRecording() {
    const isRecording = ref(false);
    const isSupported = ref(false);
    const mediaRecorder = ref<MediaRecorder | null>(null);
    const audioChunks = ref<Blob[]>([]);
    const stream = ref<MediaStream | null>(null);
    const error = ref<string | null>(null);

    // Check if MediaRecorder is supported
    const checkSupport = () => {
        const hasMediaDevices = !!navigator.mediaDevices;
        const hasGetUserMedia = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        const hasMediaRecorder = !!window.MediaRecorder;
        
        console.log('Audio recording support check:', {
            hasMediaDevices,
            hasGetUserMedia,
            hasMediaRecorder,
            isSupported: hasMediaDevices && hasGetUserMedia && hasMediaRecorder
        });
        
        isSupported.value = hasMediaDevices && hasGetUserMedia && hasMediaRecorder;
    };

    // Start recording
    const startRecording = async () => {
        try {
            error.value = null;
            
            if (!isSupported.value) {
                throw new Error('Audio recording is not supported in this browser');
            }

            // Request microphone access
            stream.value = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 44100
                } 
            });

            // Create MediaRecorder
            const options = { mimeType: 'audio/webm;codecs=opus' };
            mediaRecorder.value = new MediaRecorder(stream.value, options);
            audioChunks.value = [];

            // Handle data available
            mediaRecorder.value.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunks.value.push(event.data);
                }
            };

            // Start recording
            mediaRecorder.value.start(1000); // Collect data every second
            isRecording.value = true;

        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to start recording';
            console.error('Recording error:', err);
        }
    };

    // Stop recording
    const stopRecording = (): Promise<Blob> => {
        return new Promise((resolve, reject) => {
            if (!mediaRecorder.value || !isRecording.value) {
                reject(new Error('No active recording'));
                return;
            }

            mediaRecorder.value.onstop = () => {
                const audioBlob = new Blob(audioChunks.value, { type: 'audio/webm' });
                cleanup();
                resolve(audioBlob);
            };

            mediaRecorder.value.stop();
            isRecording.value = false;
        });
    };

    // Cleanup resources
    const cleanup = () => {
        if (stream.value) {
            stream.value.getTracks().forEach(track => track.stop());
            stream.value = null;
        }
        mediaRecorder.value = null;
        audioChunks.value = [];
    };

    // Initialize support check
    checkSupport();

    // Cleanup on unmount
    onUnmounted(() => {
        cleanup();
    });

    return {
        isRecording,
        isSupported,
        error,
        startRecording,
        stopRecording,
        checkSupport,
        cleanup
    };
}

