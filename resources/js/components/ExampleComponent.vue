<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <ul>
                            <user-item
                                v-for="user in users"
                                :user="user"
                                :key="user.name"
                            >
                                <button class="btn btn-sm btn-primary float-right"
                                        @click.prevent="placeVideoCall(user)"
                                        v-if="!callPlaced"
                                >Video Call</button>
                            </user-item>
                        </ul>
                    </div>

                </div>
            </div>
            <div class="col-md-8">
                <div class="card" v-if="callPlaced">
                    <div class="card-header">{{ calledUser.name }}</div>

                    <div class="card-body row">
                        <div class="col-12 video-container">
                            <video
                                ref="userVideo"
                                muted
                                playsinline
                                autoplay
                                class="cursor-pointer user-video"
                            />
                            <video
                                ref="partnerVideo"
                                playsinline
                                autoplay
                                class="cursor-pointer partner-video"
                                v-if="videoCallParams.callAccepted"
                            />
                            <div v-if="!videoCallParams.callAccepted && !videoCallParams.callDecline" class="partner-video d-flex justify-content-center">
                                <img src="images/phone_menu.gif" alt="">
                            </div>
                            <div v-if="videoCallParams.callDecline" class="partner-video d-flex justify-content-center">
                                <img src="images/call-decline.png" alt="">
                            </div>
                        </div>
                        <!-- End of Placing Video Call  -->
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button type="button" @click.prevent="toggleMuteAudio" class="btn btn-primary">
                            <i class="fas fa-microphone-alt-slash" v-if="mutedAudio"></i>
                            <i class="fas fa-microphone-alt" v-else></i>
                        </button>
                        <button type="button" @click.prevent="endCall" class="btn btn-danger btn-lg">
                            <i class="fas fa-phone-slash"></i>
                        </button>
                        <button type="button" @click.prevent="toggleMuteVideo" class="btn btn-success">
                            <i class="fas fa-video-slash" v-if="mutedVideo"></i>
                            <i class="fas fa-video" v-else></i>
                        </button>

                    </div>
                </div>
                <div class="card" v-if="!callPlaced">
                    <div class="card-header">Called a User</div>

                    <div class="card-body">

                    </div>
                </div>
            </div>
        </div>
        <div class="modal"  id="IncomingCall" role="dialog" data-backdrop="static">
            <div class="modal-dialog" role="document" v-if="videoCallParams.receivingCall && callerInfo">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title"> Incoming Call From <b>{{ callerInfo.name}}</b></h3>
                    </div>
                    <div class="modal-body d-flex justify-content-center">
                        <img src="images/phone_menu.gif" alt="">
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" @click.prevent="acceptCall" class="btn btn-success"><i class="fas fa-phone-volume"></i> Accept</button>
                        <button type="button" @click.prevent="declineCall" class="btn btn-danger"> <i class="fas fa-phone-slash"></i> Decline</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import Peer from "simple-peer";
import { getPermissions } from "../helpers";
import UserItem from "./UserItem";
    export default {
        components: {UserItem},
        props:[
            'users',
            "turn_url",
            "turn_username",
            "turn_credential",
        ],
        data(){
            return{
                callPlaced: false,
                calledUser: null,
                callerInfo: null,
                mutedAudio: false,
                mutedVideo: false,
                videoCallParams:{
                    channel: '',
                    stream: null,
                    users: [],
                    receivingCall: false,
                    caller: null,
                    callerSignal: null,
                    callAccepted: false,
                    callDecline: false,
                    hostPeer: null,
                    receiverPeer: null
                }
            }
        },
        mounted() {
            this.initializeChannel();
            this.initializeCallListeners();
        },
        methods:{
            initializeChannel() {
                this.videoCallParams.channel = window.Echo.join(`presence-video-channel.${authUser.id}`);
            },
            initializeCallListeners(){
                this.videoCallParams.channel.here((users) =>{
                    this.videoCallParams.users = users;
                });

                this.videoCallParams.channel.joining((user) =>{
                    const joiningUserIdx = this.videoCallParams.users.findIndex(
                        (userItem)=> userItem.id === user.id
                    )
                    if(joiningUserIdx < 0){
                        this.videoCallParams.users.push(user);
                    }
                });

                this.videoCallParams.channel.leaving((user) =>{
                    const  leavingUserIdx = this.videoCallParams.users.findIndex(
                        (userItem) => userItem.id === user.id
                    );

                    if(leavingUserIdx >= 0){
                        this.videoCallParams.users.splice(leavingUserIdx, 1);
                    }
                });

                this.videoCallParams.channel.listen('StartVideoChat', ({data}) =>{
                    if(data.type === "incomingCall"){
                        if(this.videoCallParams.caller || this.videoCallParams.callerSignal || this.videoCallParams.callAccepted || this.callPlaced){
                            this.onAnotherCall(data.from);
                            return false;
                        }
                        const callerSignal = {
                            ...data.signalData,
                            sdp: `${data.signalData.sdp}\n`
                        }

                        this.videoCallParams.receivingCall = true;
                        this.videoCallParams.caller = data.from;
                        this.videoCallParams.callerSignal = callerSignal;
                        this.callerInfo = {
                            id: this.videoCallParams.caller,
                            name: data.callerName
                        }
                        $('#IncomingCall').modal('show');
                    }
                    if(data.type === "endCall"){
                        this.videoCallParams.receivingCall = false;
                        this.videoCallParams.caller = null;
                        this.videoCallParams.callerSignal = null;
                        this.callerInfo = null;
                        $('#IncomingCall').modal('hide');
                        this.resetData();
                    }

                    if(data.type === "callAccepted"){
                        if (data.signal.renegotiate){
                            console.log('renegotiate')
                        }

                        if (data.signal.sdp){
                            this.videoCallParams.callAccepted = true;
                            const updatedSignal = {
                                ...data.signal,
                                sdp: `${data.signal.sdp}\n`
                            }
                            this.videoCallParams.hostPeer.signal(updatedSignal);
                        }
                    }

                    if(data.type === "callDeclined"){
                        this.videoCallParams.callDecline = true;
                        this.videoCallParams.callAccepted = false;
                        this.stopStreamVideo(this.$refs.userVideo);
                        this.videoCallParams.hostPeer.destroy();
                        setTimeout(()=>{
                            this.videoCallParams.callDecline = false;
                            this.callPlaced = false;
                            this.calledUser = null;
                        }, 2000);
                    }
                    if(data.type === "onAnotherCall"){
                        this.videoCallParams.callDecline = true;
                        this.videoCallParams.callAccepted = false;
                        alert('User in another Call');
                        this.stopStreamVideo(this.$refs.userVideo);
                        this.videoCallParams.hostPeer.destroy();
                        setTimeout(()=>{
                            this.videoCallParams.callDecline = false;
                            this.callPlaced = false;
                            this.calledUser = null;
                        }, 2000);
                    }
                })
            },
            getPermissions(){
                return getPermissions()
                .then((stream) =>{
                    this.videoCallParams.stream = stream;
                    if(this.$refs.userVideo){
                        this.$refs.userVideo.srcObject= stream;
                    }
                })
                .catch((error)=>{
                    console.log(error);
                    alert('permission not set');
                })
            },
            async placeVideoCall(user){
                this.callPlaced = true;
                this.calledUser = user;
                await this.getPermissions();
                this.videoCallParams.hostPeer = new Peer({
                    initiator: true,
                    trickle: false,
                    stream: this.videoCallParams.stream,
                    // TODO if not work than add ice server
                });

                this.videoCallParams.hostPeer.on("signal", (data)=>{
                    axios
                        .post("/video/call-user", {
                            user_to_call: user.id,
                            signal_data: data,
                            from: authUser.id,
                        })
                        .then(() => {})
                        .catch((error) => {
                            console.log(error);
                        });
                });

                this.videoCallParams.hostPeer.on("stream" , (stream) =>{
                    console.log('call stream');
                    if(this.$refs.partnerVideo){
                        this.$refs.partnerVideo.srcObject = stream;
                    }
                });
                this.videoCallParams.hostPeer.on("connect", ()=>{
                    console.log('Call connect');
                });
                this.videoCallParams.hostPeer.on("error", (err)=>{
                    console.log('call error');
                    console.log(err);
                });
                this.videoCallParams.hostPeer.on("close", ()=>{
                    console.log('Call close');
                });

                // TODO maybe call accept listen will be here
            },
            async acceptCall(){
                this.calledUser = this.callerInfo;
                this.callPlaced = true;
                this.videoCallParams.callAccepted = true;
                await this.getPermissions();

                this.videoCallParams.receiverPeer = new Peer({
                    initiator: false,
                    trickle: false,
                    stream: this.videoCallParams.stream
                })
                this.videoCallParams.receivingCall = false;
                this.callerInfo = null;
                $('#IncomingCall').modal('hide');
                this.videoCallParams.receiverPeer.on("signal", (data)=>{
                    axios
                        .post("/video/accept-call", {
                            signal: data,
                            to: this.videoCallParams.caller,
                        })
                        .then(() => {})
                        .catch((error) => {
                            console.log(error);
                        });
                });

                this.videoCallParams.receiverPeer.on('stream', (stream) =>{
                    this.videoCallParams.callAccepted = true;
                    if(this.$refs.partnerVideo){
                        this.$refs.partnerVideo.srcObject = stream;
                    }
                })

                this.videoCallParams.receiverPeer.on("connect", ()=>{
                    console.log('Peer connected');
                    this.videoCallParams.callAccepted = true;
                });

                this.videoCallParams.receiverPeer.on("error", (err) => {
                    console.log(err);
                });

                this.videoCallParams.receiverPeer.on("close", () => {
                    this.endCall();
                });

                this.videoCallParams.receiverPeer.signal(this.videoCallParams.callerSignal);
            },
            declineCall() {
                $('#IncomingCall').modal('hide');
                this.videoCallParams.receivingCall = false;

                axios
                    .post("/video/decline-call", {
                        to: this.videoCallParams.caller,
                    })
                    .then(() => {})
                    .catch((error) => {
                        console.log(error);
                    });
                this.resetData();
            },
            onAnotherCall(id){
                axios
                    .post("/video/another-call", {
                        to: id,
                    })
                    .then(() => {})
                    .catch((error) => {
                        console.log(error);
                    });
            },
            endCall(){
                if (!this.mutedVideo) this.toggleMuteVideo();
                if (!this.mutedAudio) this.toggleMuteAudio();
                axios
                    .post("/video/end-call", {
                        to: this.calledUser.id,
                    })
                    .then(() => {})
                    .catch((error) => {
                        console.log(error);
                    });
                this.videoCallParams.callDecline= true;
                this.stopStreamVideo(this.$refs.userVideo);
                if (this.videoCallParams.hostPeer){
                    this.videoCallParams.hostPeer.destroy();
                }
                if(this.videoCallParams.receiverPeer){
                    this.videoCallParams.receiverPeer.destroy();
                }
                setTimeout(()=>{
                    this.callPlaced = false;
                    this.calledUser = null;
                    this.resetData();
                }, 200);

            },
            resetData(){
                this.calledUser = null;
                this.callPlaced = false;
                this.videoCallParams.callAccepted = false;
                this.videoCallParams.callDecline = false;
                this.videoCallParams.callerSignal=null;
                this.videoCallParams.caller=null;
            },
            stopStreamVideo(videoElem){
                const stream = videoElem.srcObject;
                if (stream){
                    const tracks = stream.getTracks();
                    tracks.forEach((track) =>{
                        track.stop();
                    })
                    videoElem.srcObject = null;
                }

            },
            toggleMuteAudio() {
                if (this.mutedAudio) {
                    this.$refs.userVideo.srcObject.getAudioTracks()[0].enabled = true;
                    this.mutedAudio = false;
                } else {
                    this.$refs.userVideo.srcObject.getAudioTracks()[0].enabled = false;
                    this.mutedAudio = true;
                }
            },

            toggleMuteVideo() {
                if (this.mutedVideo) {
                    this.$refs.userVideo.srcObject.getVideoTracks()[0].enabled = true;
                    this.mutedVideo = false;
                } else {
                    this.$refs.userVideo.srcObject.getVideoTracks()[0].enabled = false;
                    this.mutedVideo = true;
                }
            },
        },

    }
</script>
<style scoped>
#video-row {
    width: 100%;
}

#incoming-call-card {
    border: 1px solid #0acf83;
}

.video-container {
    width: 100%;
    height: 500px;
    max-height: 50vh;
    margin: 0 auto;
    border: 1px solid #0acf83;
    position: relative;
    box-shadow: 1px 1px 11px #9e9e9e;
    background-color: #fff;
}

.video-container .user-video {
    width: 30%;
    position: absolute;
    left: 10px;
    bottom: 10px;
    border: 1px solid #fff;
    border-radius: 6px;
    z-index: 2;
}

.video-container .partner-video {
    width: 100%;
    height: 100%;
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    top: 0;
    z-index: 1;
    margin: 0;
    padding: 0;
}

.video-container .action-btns {
    position: absolute;
    bottom: 20px;
    left: 50%;
    margin-left: -50px;
    z-index: 3;
    display: flex;
    flex-direction: row;
}

/* Mobiel Styles */
@media only screen and (max-width: 768px) {
    .video-container {
        height: 50vh;
    }
}
</style>
