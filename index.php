<?php
// NOTE: This example uses the next generation Twilio helper library - for more
// information on how to download and install this version, visit
// https://www.twilio.com/docs/libraries/php
require_once __DIR__ . '/vendor/autoload.php';

use Twilio\Rest\Client;

// Your Account Sid and Auth Token from twilio.com/user/account
$sid = "AC6bd28151d4d6983302c46e6b03b2ba31";
$token = "0826e000a5fc2cc13b6d58b108810db9";

// Initialize the client
$client = new Client($sid, $token);

//Creates a token
$token = $client->tokens->create();

//echo json_encode($token->iceServers);
$servers = [];

foreach ($token->iceServers as $turn) {
    $temp = [];

    $temp['urls'] = $turn['url'];

    if (isset($turn['username'])) {
        $temp['username'] = $turn['username'];
        $temp['credential'] = $turn['credential'];
    }

    $servers[] = $temp;
}

?>

<html>

<head>
   <!--  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous"> -->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
    <script src="https://js.pusher.com/4.1/pusher.min.js"></script>
</head>

<body>
    <h3>Demo Video Call</h3>
    <input id="username" value="" type="text" />
    <input id="friend" value="" type="text" />
    <button id="make_call" type="button" class="btn btn-danger">CALL</button>
    <section>
        <video id="video_local" autoplay>
        </video>
    </section>
    <section>
        <video id="video_remote" autoplay>
        </video>
    </section>
    <!-- Latest compiled and minified JavaScript -->
    <!-- <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script> -->
    <script>
    var localVideo = document.querySelector('#video_local');
    var remoteVideo = document.querySelector('#video_remote');
    var peerConnection;
    var localDesc;
    var localStream;
    var hasAddTrack;
    var mediaConstraints = {
            audio: true,
            video: {
                facingMode: 'user',
            },
    };

    function gotStream(localStream) {
        console.log('Received local stream');
        // localVideo.srcObject = stream;
        // localStream = stream;
        //localVideo.src = window.URL.createObjectURL(stream);
        //
      localVideo.src = window.URL.createObjectURL(localStream);
      localVideo.srcObject = localStream;
    }

    function handleTrackEvent(event) {
      console.log("*** Track event");
      remoteVideo.srcObject = event.streams[0];
    }

    function handleAddStreamEvent(event) {
      console.log("*** Stream added");
      remoteVideo.srcObject = event.stream;
    }

    function handleNegotiationNeededEvent() {
      console.log("*** Negotiation needed");

      console.log("---> Creating offer");
      peerConnection.createOffer().then(function(offer) {
        console.log("---> Creating new description object to send to remote peer");
        return peerConnection.setLocalDescription(offer);
      })
      .then(function() {
        console.log("---> Sending offer to remote peer");
        // sendToServer({
        //   name: myUsername,
        //   target: targetUsername,
        //   type: "video-offer",
        //   sdp: myPeerConnection.localDescription
        // });

        $.get('/webrtc/pubsub.php', {
            from: $('#username').val(),
            to: $('#friend').val(),
            on: 'on-callrequest',
            desc: JSON.stringify(peerConnection.localDescription),
        });
      })
      .catch(function(err) {
        console.log(err, 'Error creating offer..');
      });
    }

    function onIceCandidate(event) {
        //console.log('ICE candidate detected...');

        // var current = $('#ice_local').val();
        // $('#ice_local').val(current + '\n' + JSON.stringify(event.candidate));

        if(event.candidate){
            $.get('/webrtc/pubsub.php', {
                from: $('#username').val(),
                to: $('#friend').val(),
                on: 'on-addicecandidate',
                candidate: JSON.stringify(event.candidate),
            });
        }

    }

    function onIceStateChange(event) {
        //console.log('ICE CHANGE');
    }

    function receiveCallRequest(from, desc)
    {
        console.log('Call requested...');

        var desc = JSON.parse(desc);

        peerConnection = new RTCPeerConnection({
            'iceServers': <?php echo json_encode($servers); ?>
        });

        peerConnection.onicecandidate = function(e) {
            onIceCandidate(e);
        };
        peerConnection.oniceconnectionstatechange = function(e) {
            onIceStateChange(e);
        };

        peerConnection.onnegotiationneeded = handleNegotiationNeededEvent;

        hasAddTrack = (peerConnection.addTrack !== undefined);

        if (hasAddTrack) {
            peerConnection.ontrack = handleTrackEvent;
        } else {
            peerConnection.onaddstream = handleAddStreamEvent;
        }

        var descRemote = new RTCSessionDescription(desc);

        peerConnection.setRemoteDescription(descRemote).then(function () {
            console.log("Setting up the local media stream...");
            return navigator.mediaDevices.getUserMedia(mediaConstraints);
        })
        .then(function(stream) {
            console.log("-- Local video stream obtained");
            localStream = stream;
            localVideo.src = window.URL.createObjectURL(localStream);
            localVideo.srcObject = localStream;

            if (hasAddTrack) {
              console.log("-- Adding tracks to the RTCPeerConnection");
              localStream.getTracks().forEach(track =>
                    peerConnection.addTrack(track, localStream)
              );
            } else {
              console.log("-- Adding stream to the RTCPeerConnection");
              peerConnection.addStream(localStream);
            }
        })
        .then(function() {
            console.log("------> Creating answer");
            // Now that we've successfully set the remote description, we need to
            // start our stream up locally then create an SDP answer. This SDP
            // data describes the local end of our call, including the codec
            // information, options agreed upon, and so forth.
            return peerConnection.createAnswer();
        })
        .then(function(answer) {
            console.log("------> Setting local description after creating answer");
            // We now have our answer, so establish that as the local description.
            // This actually configures our end of the call to match the settings
            // specified in the SDP.
            return peerConnection.setLocalDescription(answer);
        })
        .then(function() {
            // var msg = {
            //   name: myUsername,
            //   target: targetUsername,
            //   type: "video-answer",
            //   sdp: myPeerConnection.localDescription
            // };

            // We've configured our end of the call now. Time to send our
            // answer back to the caller so they know that we want to talk
            // and how to talk to us.

            console.log("Sending answer packet back to other peer");
            //sendToServer(msg);
            //
            $.get('/webrtc/pubsub.php', {
                from: $('#username').val(),
                to: $('#friend').val(),
                on: 'on-callaccepted',
                desc: JSON.stringify(peerConnection.localDescription),
            });
        })
        .catch(function(err) {
            console.log(err, 'Error in accepting remote connection..');
        });

        // var remoteDesc = JSON.parse(desc)

        // peerConnection.setRemoteDescription(remoteDesc,
        //     function() {
        //         console.log('Success setting Remote Description');

        //         peerConnection.createAnswer(function(desc) {

        //             $.get('/webrtc/pubsub.php', {
        //                 from: $('#username').val(),
        //                 to: from,
        //                 on: 'on-callaccepted',
        //                 desc: JSON.stringify(desc),
        //             });

        //         }, function(err) {
        //             console.log(err, 'Error creating answer');
        //         });

        //     }, function(err) {
        //         console.log(err, 'Error setting Remote Description');
        //     });
    }

    function receiveCallAccept(from, desc)
    {
        console.log('Call accepted...');

        var desc = JSON.parse(desc);

        var remoteDesc = new RTCSessionDescription(desc);
        peerConnection.setRemoteDescription(remoteDesc).catch(function(err) {
            console.log(err, 'Error accepting call...')
        });

        // peerConnection.setRemoteDescription(remoteDesc,
        //     function() {
        //         console.log('Success setting Remote Description');

        //         peerConnection.ontrac = gotRemoteStream;

        //     }, function(err) {
        //         console.log(err, 'Error setting Remote Description');
        //     });
    }

    function gotRemoteStream(e) {
      if (remoteVideo.srcObject !== e.streams[0]) {
        remoteVideo.srcObject = e.streams[0];
        console.log('Remote received remote stream');
      }
    }

    $('#make_call').click(function() {
        console.log('Starting call...');

        peerConnection = new RTCPeerConnection({
            'iceServers': <?php echo json_encode($servers); ?>
        });

        peerConnection.onicecandidate = function(e) {
            onIceCandidate(e);
        };
        peerConnection.oniceconnectionstatechange = function(e) {
            onIceStateChange(e);
        };

        peerConnection.onnegotiationneeded = handleNegotiationNeededEvent;

        hasAddTrack = (peerConnection.addTrack !== undefined);

        if (hasAddTrack) {
            peerConnection.ontrack = handleTrackEvent;
        } else {
            peerConnection.onaddstream = handleAddStreamEvent;
        }

        console.log('requesting webcam access...');

        navigator.mediaDevices.getUserMedia(mediaConstraints).then(function(localStream) {
          console.log("-- Local video stream obtained");
          localVideo.src = window.URL.createObjectURL(localStream);
          localVideo.srcObject = localStream;

          if (hasAddTrack) {
            console.log("-- Adding tracks to the RTCPeerConnection");
            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
          } else {
            console.log("-- Adding stream to the RTCPeerConnection");
            peerConnection.addStream(localStream);
          }
        }).catch(function(err) {
            alert(err);
        });

        // peerConnection.createOffer({
        //     offerToReceiveAudio: 1,
        //     offerToReceiveVideo: 1
        // }).then(function(desc) {

        //     localDesc = desc;

        //     peerConnection.setLocalDescription(desc)
        //         .then(function() {
        //             console.log('Local Connected...');

        //             $.get('/webrtc/pubsub.php', {
        //                 from: $('#username').val(),
        //                 to: $('#friend').val(),
        //                 on: 'on-callrequest',
        //                 desc: JSON.stringify(desc),
        //             });

        //         }, function() {
        //             console.log('Local unable to connect...');
        //         });

        // }, function(err) {
        //     console.log(err);
        // });

    });

    // Enable pusher logging - don't include this in production
    //Pusher.logToConsole = true;

    var pusher = new Pusher('cc652d98262ccd7cb9df', {
      cluster: 'ap1',
      encrypted: true
    });

    var channel = pusher.subscribe('my-channel');

    channel.bind('on-callrequest', function(data) {

        console.log('Call requested..');
        if(data.to){

            if(data.to == $('#username').val()){
                //message is for me

                if(data.desc){

                    receiveCallRequest(data.from, data.desc);

                }

            }

        }

    });

    channel.bind('on-callaccepted', function(data) {

        console.log('Call accepted..');
        if(data.to){

            if(data.to == $('#username').val()){
                //message is for me

                if(data.desc){

                    receiveCallAccept(data.from, data.desc);

                }

            }

        }

    });

    channel.bind('on-addicecandidate', function(data) {

        if(data.to){

            if(data.to == $('#username').val()){
                //message is for me

                if(data.candidate){

                    console.log('Adding ice candidate...');

                    var iceCandidate = JSON.parse(data.candidate);
                    //console.log(iceCandidate);

                    if(!iceCandidate) {
                        return;
                    }

                    if(!peerConnection && peerConnection.remoteDescription){

                    }

                    peerConnection.addIceCandidate(new RTCIceCandidate(iceCandidate),
                        function() {
                            console.log('Success setting ice candidate..');
                        },
                        function(err) {
                            console.log(err);
                        });

                }

            }

        }

    });

    </script>
</body>

</html>
