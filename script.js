// frontend/script.js
let basePath = window.location.pathname.split('/').filter(part => part)[0] || '';
const baseURL = `${window.location.origin}${basePath ? '/' + basePath : ''}`;

document.addEventListener('DOMContentLoaded', () => {
    const topicDropdown = document.getElementById('topicDropdown');
    const phrasesContainer = document.getElementById('phrasesContainer');
    const generatePhrasesBtn = document.getElementById('generatePhrasesBtn');
    const newTopicInput = document.getElementById('newTopicInput');
    const clearCacheBtn = document.getElementById('clearCacheBtn');
    const deleteListBtn = document.getElementById('deleteListBtn');
    const ttsServiceDropdown = document.getElementById('ttsServiceDropdown');
    const languageDropdown = document.getElementById('languageDropdown');
    const speedDropdown = document.getElementById('speedDropdown'); // Added
    
    let wordLists = {}; // Store word lists locally to avoid redundant fetches

    // Helper Functions for localStorage
    function saveSelectedTopic(topic) {
        localStorage.setItem('selectedTopic', topic);
    }

    function saveSelectedTTSService(service) {
        localStorage.setItem('selectedTTSService', service);
    }

    function saveSelectedLanguage(languageCode) {
        localStorage.setItem('selectedLanguage', languageCode);
    }

    function saveSelectedSpeed(speed) {
        localStorage.setItem('selectedSpeed', speed);
    }

    function getSavedSelectedTopic() {
        return localStorage.getItem('selectedTopic');
    }

    function getSavedSelectedTTSService() {
        return localStorage.getItem('selectedTTSService');
    }

    function getSavedSelectedLanguage() {
        return localStorage.getItem('selectedLanguage') || 'ru'; // Default to 'ru' if not set
    }

    function getSavedSelectedSpeed() {
        return localStorage.getItem('selectedSpeed') || '1'; // Default to '1' if not set
    }

    // Fetch and populate languages on page load
    fetch('backend/get_word_lists.php?action=get_languages')
        .then(response => {
            if (!response.ok) {
                throw new Error(`Failed to fetch languages (${response.status})`);
            }
            return response.json();
        })
        .then(languages => {
            populateLanguagesDropdown(languages);
            const savedLanguage = getSavedSelectedLanguage();
            if (languages.some(lang => lang.code === savedLanguage)) {
                languageDropdown.value = savedLanguage;
            } else {
                languageDropdown.value = languages[0].code; // Default to first language if saved language is unavailable
            }
        })
        .catch(error => {
            console.error('Error fetching languages:', error);
            alert('Failed to load language options. Please try again later.');
        });

    // Fetch and populate word lists on page load, pass the saved topic and language
    const savedTopic = getSavedSelectedTopic();
    const savedLanguage = getSavedSelectedLanguage();
    fetchWordLists(savedTopic, savedLanguage);

    // Set the TTS service dropdown to the saved preference
    const savedTTSService = getSavedSelectedTTSService();
    if (savedTTSService && (savedTTSService === 'google' || savedTTSService === 'openai')) {
        ttsServiceDropdown.value = savedTTSService;
    } else {
        // Default to Google if no preference is saved
        ttsServiceDropdown.value = 'openai';
    }

    // Set the speed dropdown to the saved preference
    const savedSpeed = getSavedSelectedSpeed();
    speedDropdown.value = savedSpeed;

    // Event listener for generating new phrases
    generatePhrasesBtn.addEventListener('click', () => {
        const topic = newTopicInput.value.trim();
        const languageCode = languageDropdown.value;
        if (topic === '') {
            alert('Please enter a topic.');
            return;
        }
        generatePhrases(topic, languageCode);
    });

    // Event listener for clearing cache
    clearCacheBtn.addEventListener('click', () => {
        if (confirm('Are you sure you want to clear the audio cache?')) {
            clearCache();
        }
    });

    // Event listener for deleting selected list
    deleteListBtn.addEventListener('click', () => {
        const selectedTopic = topicDropdown.value;
        const languageCode = languageDropdown.value;
        if (!selectedTopic) {
            alert('No topic selected.');
            return;
        }
        if (confirm(`Are you sure you want to delete the list "${selectedTopic}"?`)) {
            deleteList(selectedTopic, languageCode);
        }
    });

    // Event listener for changing selected topic
    topicDropdown.addEventListener('change', () => {
        const selected = topicDropdown.value;
        const languageCode = languageDropdown.value;
        displayPhrases(selected, languageCode);
        saveSelectedTopic(selected); // Save to localStorage
    });

    // Event listener for changing selected TTS service
    ttsServiceDropdown.addEventListener('change', () => {
        const selectedService = ttsServiceDropdown.value;
        saveSelectedTTSService(selectedService); // Save to localStorage
    });

    // Event listener for changing selected language
    languageDropdown.addEventListener('change', () => {
        const selectedLanguage = languageDropdown.value;
        saveSelectedLanguage(selectedLanguage); // Save to localStorage
        fetchWordLists(null, selectedLanguage); // Fetch word lists for the new language
    });

    // Event listener for changing selected speed
    speedDropdown.addEventListener('change', () => {
        const selectedSpeed = speedDropdown.value;
        saveSelectedSpeed(selectedSpeed); // Save to localStorage
    });

    // Function to populate languages dropdown
    function populateLanguagesDropdown(languages) {
        languageDropdown.innerHTML = '';
        languages.forEach(lang => {
            const option = document.createElement('option');
            option.value = lang.code;
            option.textContent = lang.name;
            languageDropdown.appendChild(option);
        });
    }

    // Function to fetch word lists from backend
    function fetchWordLists(selectedTopic = null, languageCode = 'ru') {
        fetch(`backend/get_word_lists.php?action=get_word_lists&language=${encodeURIComponent(languageCode)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Network response was not ok (${response.status})`);
                }
                return response.json();
            })
            .then(data => {
                wordLists = data; // Store fetched word lists
                populateDropdown(wordLists, selectedTopic);
                if (topicDropdown.options.length > 0) {
                    const topicToDisplay = selectedTopic && wordLists[selectedTopic] ? selectedTopic : topicDropdown.value;
                    displayPhrases(topicToDisplay, languageCode);
                    saveSelectedTopic(topicToDisplay); // Save to localStorage
                } else {
                    phrasesContainer.innerHTML = '<p>No word lists available. Please generate a new topic.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching word lists:', error);
                phrasesContainer.innerHTML = '<p>Error fetching word lists. Please try again later.</p>';
            });
    }

    // Function to populate the topic dropdown
    function populateDropdown(wordLists, selectedTopic = null) {
        topicDropdown.innerHTML = ''; // Clear existing options

        for (const key in wordLists) {
            const option = document.createElement('option');
            option.value = key; // Retain the full key for backend

            // Extract the topic name by removing the language code and number
            // Assumes the format: "topic_languagecode_number"
            const parts = key.split('_');
            if (parts.length >= 3) {
                parts.pop(); // Remove the number (e.g., "1")
                parts.pop(); // Remove the language code (e.g., "ru")
                const topicName = parts.join(' ').replace(/\b\w/g, char => char.toUpperCase()); // Capitalize each word
                option.textContent = topicName; // Display "Basic Hello Word"
            } else {
                // Fallback in case the key doesn't follow the expected format
                option.textContent = key;
            }

            topicDropdown.appendChild(option); // Add the option to the dropdown
        }

        // If a topic was previously selected, re-select it
        if (selectedTopic && wordLists[selectedTopic]) {
            topicDropdown.value = selectedTopic;
        }
    }

    // Function to display phrases for a selected topic and language
    function displayPhrases(topic, languageCode) {
        phrasesContainer.innerHTML = '';
        const phrases = wordLists[topic];
        if (!phrases || phrases.length === 0) {
            phrasesContainer.innerHTML = '<p>No phrases available for this topic.</p>';
            return;
        }

        phrases.forEach(pair => {
            const [english, translation] = pair;
            const card = document.createElement('div');
            card.className = 'phrase-card';

            const button = document.createElement('button');
            button.className = 'phrase-button';
            button.textContent = english;
            // Pass both English and translation phrases along with selected TTS service, language, and speed
            button.addEventListener('click', () => playAudio(english, translation, languageCode));

            const transDiv = document.createElement('div');
            transDiv.className = 'translation';
            transDiv.textContent = translation;

            card.appendChild(button);
            card.appendChild(transDiv);
            phrasesContainer.appendChild(card);
        });
    }

    // Function to generate new phrases
    function generatePhrases(topic, languageCode) {
        fetch('backend/generate_phrases.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ topic, language: languageCode })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server responded with status ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(`Generated phrases for topic "${data.topic}" in ${data.language_name}.`);
                    newTopicInput.value = '';
                    fetchWordLists(data.topic, languageCode); // Pass the new topic and language to fetchWordLists
                } else {
                    alert(`Error: ${data.error}`);
                }
            })
            .catch(error => {
                console.error('Error generating phrases:', error);
                alert('Failed to generate phrases. Please try again.');
            });
    }

    // Function to play audio for a given text and language
    function playAudio(english, translation, languageCode) {
        const selectedTTS = ttsServiceDropdown.value; // Get selected TTS service
        const selectedSpeed = speedDropdown.value; // Get selected speed

        fetch('backend/play_audio.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                filename: english, 
                text: translation, 
                tts_service: selectedTTS, // Include selected TTS service
                language: languageCode, // Include selected language
                speed: selectedSpeed // Include selected speed
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server responded with status ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.audio_url) {
                    const audio = new Audio(data.audio_url);
                    audio.play();
                } else {
                    alert(`Error: ${data.error}`);
                }
            })
            .catch(error => {
                console.error('Error playing audio:', error);
                alert('Failed to play audio. Please try again.');
            });
    }

    // Function to clear audio cache
    function clearCache() {
        fetch('backend/clear_cache.php', {
            method: 'POST'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server responded with status ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Audio cache cleared successfully.');
                } else {
                    alert(`Error: ${data.error}`);
                }
            })
            .catch(error => {
                console.error('Error clearing cache:', error);
                alert('Failed to clear cache. Please try again.');
            });
    }

    // Function to delete a word list
    function deleteList(topic, languageCode) {
        fetch('backend/delete_list.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ topic, language: languageCode })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server responded with status ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(`Deleted list "${topic}" successfully.`);
                    fetchWordLists(null, languageCode); // Refresh word lists after deletion
                } else {
                    alert(`Error: ${data.error}`);
                }
            })
            .catch(error => {
                console.error('Error deleting list:', error);
                alert('Failed to delete the list. Please try again.');
            });
    }
});
