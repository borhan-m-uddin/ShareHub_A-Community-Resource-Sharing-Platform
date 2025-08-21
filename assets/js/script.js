// Main JavaScript file
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded successfully!');
    
    // Example function
    function showAlert() {
        alert('Hello from JavaScript!');
    }
    
    // Example AJAX request
    function fetchData() {
        fetch('api/data.php')
            .then(response => response.json())
            .then(data => {
                console.log('Data received:', data);
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }
});
