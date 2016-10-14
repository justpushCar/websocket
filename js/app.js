/**
 * Created by Administrator on 2016/10/14 0014.
 */
$().ready(function () {

    window.flag=0;


    $('#name').bind('blur',function(){
        $('#name').css({
            'border' :'none',
        });
            var name=$(this).val();
            if(name==''){
                 return;
            }
            $('#logo').html('');
            if($('#logo1')){
                $('#logo1').remove();
            }
            var div= $('<div></div>');
            div.attr('id','logo1');
            div.css({
                'width':'90px',
                'height':'90px',
                'backgroundColor':'rgb('+Math.random()*255+','+Math.random()*255+','+Math.random()*255+')',
                'position': 'relative',
                'left': '50%',
                'z-index': '1000',
                'marginLeft': '-45px',
                'height': '90px',
                'borderRadius': '45px',
                'textAlign': 'center',
                'lineHeight': '90px',
                'backgroundColor':'rgb('+ parseInt(Math.random()*254) +','+ parseInt(Math.random()*254) +','+ parseInt(Math.random()*254) +')',
                'fontSize':'25px',
                'color':'white'
            });
            div.html(name);
            $('#logo').append(div);
    });
    $('.submit').bind('click',function(){
        var name=$('#name').val();
        if(name==''){
            $('#name').css({
                'border' :'solid',
                'borderWidth':'1px',
                'borderColor': 'red'
            });
            return;
        }
         $('.input').slideUp(500);

         init();
         // $('#showname').html(name);


    })


    function init() {
        var name=$('#name').val();
        var color=$('#logo1').css('backgroundColor');
        var wsServer = 'ws://127.0.0.1:8080';
        ws = new WebSocket(wsServer);

        //握手监听函数
        ws.onopen=function(){
            flag=1;
            //状态为1证明握手成功，然后把client自定义的名字发送过去
            if(ws.readyState==1){
                //握手成功后对服务器发送信息
                ws.send("type=add&color="+color+'&name='+name);
                showinfo(name);
            }
        }

        ws.onmessage = function (msg){
            console.log(msg);
            var msgObj=$.parseJSON(msg.data);
            console.log(msgObj);
            if(msgObj.type=='add'){
               var html='<li class="date">'+msgObj.time+'  '+msgObj.msg+'</li>';
                $('.content-list').append(html);
                scroll();
            }else if(msgObj.type=='remove'){
                var html='<li class="date">'+msgObj.time+'  '+msgObj.msg+'</li>';
                $('.content-list').append(html);
                scroll();
            }else if(msgObj.type=='rmsg'){
                var color=msgObj.color;
                var li=$('<li></li>');
                var i=$('<i></i>');
                i.attr('id','img');
                i.css('backgroundColor',color);
                i.html(msgObj.name);
                li.append(i);
                var html='<p style="margin-top: -7.5px">'+msgObj.msg+'</p>';
                li.append(html);
                $('.content-list').append(li);
                scroll();
            }

        }

        $('#send').bind('click',function () {
            var text=$('#text').val();
            if(text==''){
                return;
            }
            console.log(text);
            var name=$('#logo1').html();
            var color=$('#logo1').css('backgroundColor');
            var msg='type=sendMsg&msg='+text+'&name='+name;
            ws.send(msg);

            var li=$('<li></li>');
            var i=$('<i></i>');

            i.attr('id','img');
            i.css('backgroundColor',color);
            i.css('float','right');
            i.html(name);

            li.attr('class','s_message');
            li.css({
                'float':'right',
                'width':'100%',
            });
            li.append(i);
            var html='<p style="margin-top: 4px;float: right">'+text+'</p>';
            li.append(html);
            $('.content-list').append(li);
            $('#text').val('');
            scroll();

        });
    }
    $('.scrollbar').bind('blur',function () {
        if(!$(this).val()==''){
              $('#send').attr('class','button');
        }
    })

    $('.date').html(getNowFormatDate());

    $('.clear').bind('click',function () {
        var time = getNowFormatDate();
        $('.content-list').html('<li class="date">'+time+'</li>');
    })
    /*
      展示个人信息
     */
    function showinfo(name) {
        var html=$('#logo1');
        var color=$('#logo1').css('backgroundColor');
        html.css({
        'borderRadius': '45px',
        'textAlign': 'center',
        'lineHeight': '50px',
        'fontSize': '12px',
        'color': 'white',
        'position': 'relative',
        'marginTop': '-10px',
        'marginLeft': '-10px',
        'width': '50px',
        'height': '50px',
        'left'  : '0',
        'backgroundColor':color
        })
        $('.userinfo').append(html);
        var span=$('<span></span>');
        span.html(name);
        span.css({
            'float': 'left',
            'position': 'relative',
            'top': '-40',
            'color': '#3e3e3e',
             'left': '20%'
        })
        $('.userinfo').append(span);
    }

    function getNowFormatDate() {
        var date = new Date();
        var seperator1 = "-";
        var seperator2 = ":";
        var month = date.getMonth() + 1;
        var strDate = date.getDate();
        if (month >= 1 && month <= 9) {
            month = "0" + month;
        }
        if (strDate >= 0 && strDate <= 9) {
            strDate = "0" + strDate;
        }
        var currentdate = date.getFullYear() + seperator1 + month + seperator1 + strDate
            + " " + date.getHours() + seperator2 + date.getMinutes()
            + seperator2 + date.getSeconds();
        return currentdate;
    }

    function scroll() {
        var height=$('.content-list').height();
        $('.content-list').scrollTop(height);
    }
})