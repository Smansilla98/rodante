(function () {
    var scale =
        localStorage.getItem('rodante-scale') ||
        localStorage.getItem('rodanta-scale') ||
        localStorage.getItem('tn-scale') ||
        'md';
    document.documentElement.dataset.type = scale;
})();
