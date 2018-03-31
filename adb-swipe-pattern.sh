#!/bin/sh
#
# Android swipe around corners (also: swipe lock patterns)
# Author: Matt Wilson 
# Modified: einsiedlerkrebs
# Modified: mnagel - 2018 - used to unlock a OnePlus 3 with broken display
# Licence: Free to use and share. If this helps you please buy me a beer :)
# Forked from: https://github.com/mattwilson1024/android-pattern-unlock

DEV=/dev/input/event3

# =======================================================================================================================

# Function definitions

WakeScreen() {
	if [ "$WAKE_SCREEN_ENABLED" = true ]; then
		adb shell input keyevent 26
	fi
}

SwipeUp() {
	if [ "$SWIPE_UP_ENABLED" = true ]; then
		adb shell input swipe ${SWIPE_UP_X} ${SWIPE_UP_Y_FROM} ${SWIPE_UP_X} ${SWIPE_UP_Y_TO}
	fi
}

StartTouch() {
	adb shell sendevent $DEV 3 57 14
}

SendCoordinates () {
	adb shell sendevent $DEV 3 53 $1
	adb shell sendevent $DEV 3 54 $2
	adb shell sendevent $DEV 3 58 57
	adb shell sendevent $DEV 0 0 0
}

FinishTouch() {
	adb shell sendevent $DEV 3 57 4294967295
	adb shell sendevent $DEV 0 0 0
}

# Actions

# =======================================================================================================================

#WakeScreen
#SwipeUp
StartTouch

# use adb-viewer.php to discover the coordinates required for your pattern visually
# that is soooo much more simple than the device-specific math that was done here previously...
SendCoordinates 100 200
SendCoordinates 800 750
SendCoordinates 100 400

FinishTouch
