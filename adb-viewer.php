<?php

if (isset($_GET['s'])) {
    // screenshot request
    header('Content-Type: image/png');
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    passthru('adb shell screencap -p');
    exit;
} elseif (isset($_GET['i'])) {
    // input send
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    if (preg_match('/^(swipe|tap|text|keyevent|trackball)/', $_GET['i'])) {
        passthru('adb shell input ' . $_GET['i']);
    }
    exit;
}

// Normal request, load page
?>

<img src="?s">
<div id="cover"></div>
<div id="loading">loading</div>
<form id="form">
    <button value="82">menu</button>
    <button value="3">home</button>
    <button value="4">back</button>
    <button value="5">call</button>
    <button value="6">endcall</button>
    <button value="26">power</button>
    <button value="27">camera</button>
    <button value="28">clear</button>
</form>
<input id="input">

<script>
    /** Current image on page */
    var img = document.getElementsByTagName('img')[0],
    /** New image being loaded */
        newImg,
    /** Element to run mouse events on */
        cover = document.getElementById('cover'),
    /** Loading indicator */
        loading = document.getElementById('loading'),
    /** Element for typing events */
        input = document.getElementById('input'),
    /** Position mousedown started */
        startX = null,
    /** Position mousedown started */
        startY = null,
    /** If we have another image to be queued up immediately after newImg */
        queueImg = false,
    /** Timeout for incremental image loading without activity */
        nextLoad,
    /** Incremental load delay for next image */
        nextLoadDelay = 5000,
    /** Runs triggers in order, asynchronously in this queue */
        triggerQueue = [],
    /** If currently waiting for a trigger to return */
        waiting = false,
    /** Some keyboard events which are rewritten to Android keyevents */
        keymap = {
            8: 67,  // backspace -> DEL
            38: 19, // up
            40: 20, // down
            37: 21, // left
            39: 22, // right
            13: 66  // enter
        };;

    /**
     * Load up a new screenshot.
     */
    function loadImage() {
        clearTimeout(nextLoad);

        if (newImg) {
            if (!queueImg) {
                queueImg = true;
            }
            return;
        }

        loading.className = 'loading';

        newImg = new Image();
        newImg.onload = imageLoaded;
        newImg.src = '?s&' + (+new Date());
    }

    /**
     * On screenshot load, replace the existing screenshot with the new one.
     */
    function imageLoaded() {
        img.parentNode.replaceChild(newImg, img);
        img = newImg;
        newImg = null;

        if (queueImg) {
            queueImg = false;
            nextLoadDelay = 50;
        }

        // Also trigge the next image load, slowly increasing in delay by 1s to 10s
        nextLoad = setTimeout(loadImage, nextLoadDelay);
        nextLoadDelay += 1000;
        if (nextLoadDelay > 10000) {
            nextLoadDelay = 10000;
        }

        loading.className = '';
    }

    /**
     * Sends a trigger request to adb.
     * @param {String} text
     */
    function trigger(text) {

        console.log(text);
        if (waiting) {
            triggerQueue.push(text);
            return;
        }

        waiting = true;
        loading.className = 'loading';

        var xmlhttp = new XMLHttpRequest();
        xmlhttp.open("GET", '?i=' + encodeURIComponent(text), true);
        xmlhttp.send();
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                loading.className = '';
                nextLoadDelay = 500;
                loadImage();
                waiting = false;
                if (triggerQueue.length) {
                    trigger(triggerQueue.shift());
                }
            }
        };
    }

    /**
     * Converts mouse x to full-size x for Android.
     * @param {String} x
     * @returns {number}
     */
    function adaptX(x) {
        return Math.floor(x / img.width * img.naturalWidth);
    }

    /**
     * Converts mouse y to full-size y for Android.
     * @param {String} y
     * @returns {number}
     */
    function adaptY(y) {
        return Math.floor(y / img.height * img.naturalHeight);
    }

    // On mousedown, store the start position
    cover.addEventListener('mousedown', function () {
        if (event.x > img.width || event.y > img.height) {
            startX = null;
            return;
        }

        var x = adaptX(event.x),
            y = adaptY(event.y);

        startX = x;
        startY = y;
    });

    // On mouseup, send a swipe or tap event
    cover.addEventListener('mouseup', function () {
        input.focus();

        if (startX === null) {
            return;
        }

        var x = adaptX(event.x),
            y = adaptY(event.y);

        if (Math.abs(x - startX) > 2 || Math.abs(y - startY) > 2) {
            trigger('swipe ' + startX + ' ' + startY + ' ' + x + ' ' + y);
        } else {
            trigger('tap ' + x + ' ' + y);
        }

        startX = null;
    });

    // On keydown, check if this is a special button from keymap
    window.addEventListener('keydown', function (event) {
        input.focus();

        if (event.ctrlKey || event.metaKey) {
            return;
        }

        if (keymap[event.which]) {
            event.preventDefault();
            event.cancelBubble = true;
            trigger('keyevent ' + keymap[event.which]);
            return false;
        }
    }, true);

    // On keyup in the input field, send this text to adb
    input.addEventListener('keyup', function (event) {
        event.cancelBubble = true;
        var text = input.value;
        input.value = '';

        if (text) {
            if (text === '?') {
                // special case, just "?" becomes "d" for some reason
                trigger('text "? "'); // add extra char
                trigger('keyevent 67'); // delete extra ?
            } else {
                trigger('text "' + text.replace(/(["\\])/g, "\\$1").replace(/ /g, '%s') + '"');
            }
        }
    }, true);

    // On any button click, send this specific keyevent
    Array.prototype.forEach.call(document.getElementsByTagName('button'), function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.cancelBubble = true;

            input.focus();

            trigger('keyevent ' + this.value);

            return false;
        }, true);
    });

    // Start loading next image
    nextLoad = setTimeout(loadImage, nextLoadDelay);
    input.focus();
</script>
<style>
    /* Reset */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -moz-box-sizing: border-box;
        -webkit-box-sizing: border-box;
        font-family: Helvetica Neue, Helvetica, Arial, sans-serif;
        cursor: default;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    html, body { height: 100%; width: 100%; }

    img {
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
    }

    #cover {
        width: 100%;
        height: 100%;
        position: absolute;
        top: 0;
        left: 0;
    }

    #loading {
        visibility: hidden;
        position: absolute;
        bottom: 0;
        right: 0;
        background: #06C;
        padding: .5em 1em;
        opacity: .5;
        color: #fff;
        font-weight: bold;
        pointer-events: none;
    }

    #loading.loading {
        visibility: visible;
    }

    #form {
        position: absolute;
        top: 0;
        right: 0;
        max-width: 10%;
    }

    #form button {
        display: block;
        width: 100%;
        padding: .25em;
        cursor: pointer;
    }

    #input {
        position: absolute;
        bottom: 2em;
        right: 0;
        opacity: .01;
    }
</style>
