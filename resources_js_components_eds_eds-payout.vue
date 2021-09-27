<template>
    <div>
        <div class="inner-wrap t-0 text-center" >
            <div class="form-group mt-1">
                <button class="btn btn-primary mt-2" v-on:click="connectSocket()">Чекнуть</button>
            </div>
            <p v-for="(item,index) in info">
                {{ parseInt(index)+1 }}. РВ isn: {{ item.isn }}
                <span class="text-success" v-if="item.confirmed == 1">прошел проверку</span>
                <!--span class="text-danger" v-if="item.confirmed == 0">не проверен </span-->
                <span class="text-danger" v-if="item.iin_fail == 1">не совпадает ИИН</span>
            </p>
        </div>
        <div v-show="loading" class="text-center"><img src="/images/loading.gif"></div>
        <div class="title">
            <button class="btn btn-primary mt-2" v-on:click="setQr()">Посадить QR на excel и отправить в Kias</button>
        </div>
    </div>


</template>

<script>
    const axios = require('axios');
    export default {
        name: "eds-payout",
        data() {
            return {
                confirmed: false,
                loading: false,
                seenmoney: false,
                ws: null,
                onlinePlayers: 0,
                human: {
                    pathToFile: '',
                    pathDir: '',
                },
                sign: {
                    password: '',
                    token: null,
                },
                selectedFile: '',
                selectedFileDir: '',
                base64String: 'dGVzdA==',
                selectedECPFile: '',
                signedFile:'',
                path:'',
                paths:[],
                signedFileInfo: [],
                edsConfirmed: false,
                hasConfirmed: false,
            }
        },
        props: {
            showView: String,
            doc_row_list_inner_other: Object,
            info: Object,
            classIsn: Number,
            emplIsn: Number
        },
        methods: {
            connectSocket(check){
                var vm = this;
                this.signedFile = '';
                var webSocket = new WebSocket('wss://127.0.0.1:13579');
                webSocket.onopen = function () {
                    webSocket.send('{\n' +
                        '    module: "kz.uchet.signUtil.commonUtils", \n' +
                        '    method: "getVersion", \n' +
                        '    args: [""]\n' +
                        '}');
                };
                webSocket.onmessage = function(msg) {
                    var result = JSON.parse(msg.data);
                    if(!result.success && result.errorCode === 'MODULE_NOT_FOUND'){
                        vm.installModule()
                    }else{
                        if(result.code == 200) {
                            if(check != undefined) {
                                //vm.openWindow();
                                vm.checkSigns();
                            } else {
                                vm.checkSigns();
                                //vm.getKey();
                            }
                        }
                    }
                }
                webSocket.onerror = function(msg) {
                    // TODO PUSH ERROR
                    //webSocket.close();
                    //console.log(msg);
                    if(msg.type == 'error') {
                        alert("Убедитесь пожалуйста что у Вас установлена программа NCLayer и она запущена. Программу можно скачать по адресу https://pki.gov.kz/ncalayer/");
                    }
                }
            },
            installModule: function(){
                var webSocket = new WebSocket('wss://127.0.0.1:13579');
                webSocket.onopen = function () {
                    webSocket.send('{\n' +
                        '    module: "kz.gov.pki.ncalayerservices.accessory", \n' +
                        '    method: "installBundle", \n' +
                        '    symname: "kz.uchet.signUtil" \n' +
                        '}');
                };
                webSocket.onmessage = function(msg) {
                    // console.log(msg)
                }
                webSocket.onerror = function(msg) {
                    // TODO PUSH ERROR
                    //console.log(msg);
                }
            },
            getEdsInfo(docIsn,doc_index){    // docIsn - isn документа
                let self = this;
                self.signedFileInfo = [];
                //self.loader(true);
                axios.post("/eds-by-isn", {
                    refISN: docIsn,
                    type: 'D',
                    edsType: 'cms'
                }).then((response) => {
                    this.path = response.data.result[0].filepath;
                    // console.log(response.data.result[0].filepath);
                    if(response.data.success) {
                        var obj = response.data.result;
                        if(obj.length > 0){
                            for(let index in obj) {
                                this.checkSignedFile(obj[index].filepath,docIsn,obj[index].docISN, doc_index,obj[index]);     // Проверить подписанные файлы  obj[index].docISN
                            }
                        } else {
                            //self.loader(false);
                            //self.checkContinue(doc_index+1);
                        }
                    } else {
                        alert(response.data.error);
                        self.checkContinue(doc_index+1);
                        //self.loader(false);
                    }
                });
            },
            checkSignedFile(url,refIsn,docISN, doc_index,rv_data){        // Посмотреть подписанный файл
                let self = this;
                //self.loader(true);
                if(url != ''){
                    var webSocket = new WebSocket('wss://127.0.0.1:13579');
                    //self.loader(true);
                    webSocket.onopen = function () {
                        var responseObj = {
                            module: 'kz.uchet.signUtil.commonUtils',
                            lang: 'en',
                            method: 'checkCMS',
                            args: [url]
                        };
                        webSocket.send(JSON.stringify(responseObj));
                    };
                    webSocket.onmessage = function (msg) {
                        var result = JSON.parse(msg.data);
                        if(result.code) {
                            if (result.code == 200) {
                                if(result.responseObjects.length > 0) {
                                    self.signedFileInfo = result.responseObjects;
                                    //console.log(result.responseObjects);
                                    //console.log(self.signedFileInfo[0].iin+'='+self.info[doc_index].iin);
                                    if(result.responseObjects[0].iin == self.info[doc_index].iin) {
                                        self.sendEdsInfoToKias(refIsn,docISN,doc_index); // Записываем в киас данные из подписанного файла
                                    } else {
                                        self.info[doc_index].iin_fail = 1;
                                        self.checkContinue(doc_index+1);
                                        //self.loader(false);
                                    }
                                }
                            } else {
                                alert(result.message);
                                //self.checkContinue(doc_index+1);
                                //self.loader(false);
                            }
                        }
                    }
                    webSocket.onerror = function (msg) {
                        //self.loader(false);
                        alert("Убедитесь пожалуйста что у Вас установлена программа NCLayer и она запущена. Программу можно скачать по адресу https://pki.gov.kz/ncalayer/");
                    }
                } else {
                    alert('Выберите пожалуйста файл');
                }
            },
            sendEdsInfoToKias(refIsn,docIsn,doc_index){ // docIsn - isn документа, self.isn - это исн котировки
                let self = this;
                let obj = self.signedFileInfo;
                //this.loader(true);
                for (let index in obj) {
                    axios.post("/save_eds_info", {
                        data: obj[index],
                        isn: docIsn,
                        refIsn: refIsn    //self.isn
                    }).then((response) => {
                        if (response.data.success) {
                            //console.log(doc_index+'='+this.info[doc_index].iin);
                            self.info[doc_index].confirmed = 1;
                            self.hasConfirmed = true;
                            //if(doc_index == Object.keys(self.info).length-1){
                                //self.confirmed = true;
                            //    this.saveDocument();
                            //} else {
                                self.checkContinue(doc_index+1);
                            //}
                            //self.loader(false);
                        } else {
                            self.checkContinue(doc_index+1);
                        }
                    });
                }
            },
            clearData(){
                this.sign.token = '';
                this.selectedECPFile = '';
                this.sign.password = '';
                this.signedFile = '';
                this.signedFileInfo = [];
            },
            loader(show){
                this.loading = show;
            },
            saveDocument(){
                if(this.hasConfirmed) {
                    axios.post("/save_documentpo", {
                        classISN: this.classIsn,
                        data: this.info,
                        emplISN: this.emplIsn
                    }).then((response) => {
                        this.paths = response.data.result.map(el => el.filepath);
                        this.path = response.data.result[0].filepath;
                        if (response.data.success) {
                            this.loader(false);
                        } else {
                            this.loader(false);
                        }
                    });
                } else {
                    axios.post("/save_fail_status", {
                        data: this.info,
                    }).then((response) => {
                        if (response.data.success) {
                            this.loader(false);
                        } else {
                            this.loader(false);
                        }
                    });
                }
            },
            checkSigns(){
                this.loader(true);
                if(this.info.length > 0) {
                    this.getEdsInfo(this.info[0].isn,0);
                    // for(let i = 0; Object.keys(this.info).length > i; i++){
                    //     this.getEdsInfo(this.info[i].isn,i);
                    //     if(i == Object.keys(this.info).length-1){
                    //         this.loader(false);
                    //     }
                    // }
                }
            },
            signQr(){
                axios.post("/signqr", {
                    refISN: 40475701,
                    type: 'D',
                    edsType: 'cms'
                }).then((response) => {
                    console.log(response);
                    if (response.data.success) {
                        this.loader(false);
                    } else {
                        this.loader(false);
                    }
                });
            },
            setQr(){
                axios.post("/setQrPo", {
                    refISN: 40475701,
                    path:this.path,
                    type: 'D',
                    paths:this.paths,
                    edsType: 'cms',
                    info:this.info,
                }).then((response) => {
                    console.log(response);
                    if (response.data.success) {
                        this.loader(false);
                    } else {
                        this.loader(false);
                    }
                });
            },
            checkContinue(index){
                if(index > this.info.length-1) {
                    this.saveDocument();
                    //console.log(index+'!!!');
                } else {
                    this.getEdsInfo(this.info[index].isn,index);
                    //console.log(index+'???');
                }
            }
        },

        created: function() {
            //...
        },
        watch: {
            'info': function(){
                console.log('this changed');
            }
        }


    }
</script>

<style scoped>

</style>
