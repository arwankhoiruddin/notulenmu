document.addEventListener('DOMContentLoaded', (event) => {
    document.getElementById('pwm').addEventListener('change', function() {
        var id = this.value;
        fetch('https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/' + id + '/children', {
            headers: {
                'Origin': 'http://localhost',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            var pdm = document.getElementById('pdm');
            pdm.innerHTML = '';
            data.data.forEach(item => {
                var option = document.createElement('option');
                option.value = item.id;
                option.text = item.nama;
                pdm.appendChild(option);
            });
        });
    });
});

document.addEventListener('DOMContentLoaded', (event) => {
    document.getElementById('pwm').addEventListener('change', function() {
        var id = this.value;
        fetch('https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/' + id + '/children', {
            headers: {
                'Origin': 'http://localhost',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            var pdm = document.getElementById('pdm');
            pdm.innerHTML = '';
            data.data.forEach(item => {
                var option = document.createElement('option');
                option.value = item.id;
                option.text = item.nama;
                pdm.appendChild(option);
            });
        });
    });

    document.getElementById('pdm').addEventListener('change', function() {
        var id = this.value;
        fetch('https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/' + id + '/children', {
            headers: {
                'Origin': 'http://localhost',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            var pcm = document.getElementById('pcm');
            pcm.innerHTML = '';
            data.data.forEach(item => {
                var option = document.createElement('option');
                option.value = item.id;
                option.text = item.nama;
                pcm.appendChild(option);
            });
        });
    });

    document.getElementById('pcm').addEventListener('change', function() {
    var id = this.value;
    fetch('https://cors-anywhere.herokuapp.com/https://sicara.id/api/v0/organisation/' + id + '/children', {
        headers: {
            'Origin': 'http://localhost',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        var pr = document.getElementById('prm');
        pr.innerHTML = '';
        data.data.forEach(item => {
            var option = document.createElement('option');
            option.value = item.id;
            option.text = item.nama;
            pr.appendChild(option);
        });
    });
});
});