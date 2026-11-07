"""Optional developer tests: Python stdlib only. Application needs no Python.
Usage: python tests/http_smoke.py --php C:/xampp/php/php.exe --url http://localhost/doctor-hospital
Creates and removes its own temporary accounts, doctor and bookings.
"""
import argparse, base64, datetime, html.parser, http.cookiejar, json, pathlib, re, secrets, subprocess, urllib.request, urllib.parse, urllib.error

parser = argparse.ArgumentParser()
parser.add_argument('--php', required=True)
parser.add_argument('--url', default='http://127.0.0.1:8085')
args = parser.parse_args()
root = pathlib.Path(__file__).resolve().parent.parent
prefix = 'qa_' + secrets.token_hex(6)
password = secrets.token_urlsafe(24)
passed = 0

def fixture(action):
    result = subprocess.run([args.php, str(root/'tests/http-fixture.php')], input=json.dumps({'action':action,'prefix':prefix,'password':password}), text=True, capture_output=True)
    if result.returncode: raise RuntimeError(result.stderr)
    return json.loads(result.stdout)

class Page(html.parser.HTMLParser):
    def __init__(self, source):
        super().__init__(); self.inputs=[]; self.links=[]; self.feed(source)
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='input': self.inputs.append(a)
        if tag=='a': self.links.append(a.get('href',''))
    def value(self,name):
        return next(a.get('value','') for a in self.inputs if a.get('name')==name)

class Client:
    def __init__(self): self.jar=http.cookiejar.CookieJar(); self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
    def request(self,path,data=None,files=None):
        headers={}
        if files:
            boundary='----DoctorHospital'+secrets.token_hex(8); chunks=[]
            for key,value in data.items(): chunks += [f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()]
            for key,(filename,content,mime) in files.items(): chunks += [f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode(),content,b'\r\n']
            chunks += [f'--{boundary}--\r\n'.encode()]; body=b''.join(chunks); headers['Content-Type']='multipart/form-data; boundary='+boundary
        else: body=urllib.parse.urlencode(data).encode() if data is not None else None
        url=path if path.startswith('http') else args.url.rstrip('/')+path
        try: response=self.opener.open(urllib.request.Request(url,data=body,headers=headers))
        except urllib.error.HTTPError as error: response=error
        source=response.read().decode('utf-8',errors='replace')
        return response.status,source,response.url,response.headers
    def get(self,path): return self.request(path)
    def post(self,path,data,files=None): return self.request(path,data,files)

def check(condition,message):
    global passed
    if not condition: raise AssertionError(message)
    passed+=1; print('PASS:',message)

def sign_in(client,role):
    _,source,_,_=client.get('/login.php?role='+role)
    return client.post('/login.php',{'csrf':Page(source).value('csrf'),'role':role,'username':prefix+'_'+role,'password':password})

setup=fixture('create')
try:
    if setup.get('initial_admin_tested'): check(True,'First-admin CLI creates an account from a password supplied through stdin')
    public=Client(); admin=Client(); reception=Client()
    check(public.get('/')[0]==200,'Public homepage responds successfully')
    status,source,url,headers=public.get('/admin/index.php')
    check(status==200 and '/login.php' in url,'Unauthenticated staff access redirects to login')
    status,source,url,headers=sign_in(admin,'admin')
    check(status==200 and '/admin/index.php' in url and 'Hospital overview' in source,'Super Admin login works')
    check(any(c.secure is False and c.has_nonstandard_attr('HttpOnly') and c.get_nonstandard_attr('SameSite')=='Lax' for c in admin.jar),'Local session cookies use HttpOnly and SameSite=Lax')
    status,source,url,headers=sign_in(reception,'receptionist')
    check(status==200 and '/reception/index.php' in url,'Receptionist login works')
    for path in ['/admin/index.php','/admin/doctors.php','/admin/schedules.php','/admin/receptionists.php']:
        check(reception.get(path)[0]==403,'Receptionist denied '+path)
    check(admin.post('/admin/doctors.php',{'name':'Unauthorized CSRF'})[0]==419,'Missing CSRF token rejected')
    _,source,_,_=admin.get('/admin/doctors.php'); csrf=Page(source).value('csrf')
    form={'csrf':csrf,'id':'0','name':prefix+' Doctor','specialization':'QA Medicine','qualifications':'MBBS','phone':'03001234567','active':'1'}
    status,source,_,_=admin.post('/admin/doctors.php',form,{'photo':('bad.svg',b'<svg onload="alert(1)"/>','image/svg+xml')})
    check('Use a valid JPG' in source,'Executable SVG photo uploads rejected')
    png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6i8AAAAASUVORK5CYII=')
    # Generate a reliable 2x2 PNG with stdlib, avoiding any imaging dependency.
    import zlib,struct
    def chunk(kind,data): return struct.pack('>I',len(data))+kind+data+struct.pack('>I',zlib.crc32(kind+data)&0xffffffff)
    png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',2,2,8,2,0,0,0))+chunk(b'IDAT',zlib.compress((b'\x00'+b'\x79\x1f\x35'*2)*2))+chunk(b'IEND',b'')
    status,source,_,_=admin.post('/admin/doctors.php',form,{'photo':('doctor.png',png,'image/png')})
    check(status==200 and 'Doctor saved.' in source and 'assets/uploads/' in source,'Admin adds doctor with safely re-encoded photo')
    # New doctor is the only name with this random prefix; locate its containing table row.
    row=next(row for row in re.findall(r'<tr>.*?</tr>',source,re.S) if prefix+' Doctor' in row)
    doctor=int(re.search(r'\?edit=(\d+)',row).group(1))
    date=(datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=5))).date()+datetime.timedelta(days=1))
    path='/admin/schedules.php?doctor='+str(doctor)
    _,source,_,_=admin.get(path)
    schedule={'csrf':Page(source).value('csrf'),'action':'save','weekday':str(date.isoweekday()),'booking_open':'09:00','booking_close':'16:00','consultation_start':'18:30','consultation_end':'18:40','duration_minutes':'5'}
    status,source,_,_=admin.post(path,schedule)
    check(status==200 and 'Schedule updated.' in source,'Admin creates recurring weekday schedule')
    _,source,_,_=admin.get('/admin/receptionists.php')
    user={'csrf':Page(source).value('csrf'),'name':'QA Reception','username':prefix+'_created','phone':'03001234567','password':password,'active':'1'}
    status,source,_,_=admin.post('/admin/receptionists.php',user)
    check(status==200 and 'Receptionist account saved.' in source,'Admin creates a receptionist account')
    bookpath='/book.php?doctor='+str(doctor)
    _,source,_,_=public.get(bookpath); page=Page(source)
    details={'csrf':page.value('csrf'),'submission_key':page.value('submission_key'),'date':date.isoformat(),'name':'<script>alert(1)</script> QA Patient','phone':'+923001234567','reason':'A fictional test visit'}
    status,slip,slipurl,headers=public.post(bookpath,details)
    check(status==200 and '/appointment.php?token=' in slipurl,'Patient books without login and receives private link')
    check('YOUR QUEUE TOKEN' in slip and '6:30 PM' in slip,'Confirmation displays token and estimated time')
    check('&lt;script&gt;alert(1)&lt;/script&gt;' in slip and '<script>alert(1)</script>' not in slip,'Patient input is escaped against XSS')
    check(headers.get('Referrer-Policy')=='no-referrer' and headers.get('Cache-Control')=='no-store','Private slip disables referrer sharing and caching')
    check(public.post(bookpath,details)[2]==slipurl,'Double form submission returns the same slip')
    stranger=Client(); check(stranger.get(slipurl)[0]==200,'Private link reopens in a new session')
    check(stranger.get('/appointment.php?id=1')[0]==404,'Sequential appointment IDs cannot reveal records')
    check(stranger.get('/appointment.php?token='+'0'*64)[0]==404,'Invalid confirmation secret returns 404')
    queue='/reception/queue.php?doctor='+str(doctor)+'&date='+date.isoformat()
    _,source,_,_=reception.get(queue); page=Page(source)
    walkin={'csrf':page.value('csrf'),'action':'walkin','submission_key':page.value('submission_key'),'name':'Walk-in Patient','phone':'03011234567','reason':''}
    status,source,_,_=reception.post(queue,walkin)
    check(status==200 and 'token #2' in source and '6:35 PM' in source,'Receptionist adds walk-in with next token and estimate')
    check('Fully Booked' in public.get(bookpath)[1],'Full date visibly marked Fully Booked')
    _,source,_,_=reception.get(queue+'&q=Walk-in'); check('Walk-in Patient' in source and 'QA Patient' not in source,'Queue patient search filters by name')
    page=Page(source); appointment_id=page.value('appointment_id')
    status,source,_,_=reception.post(queue,{'csrf':page.value('csrf'),'action':'status','appointment_id':appointment_id,'status':'Arrived'})
    check('Patient status updated.' in source and 'status-arrived' in source,'Receptionist marks patient arrived')
    status,source,_,_=reception.post(queue,{'csrf':Page(source).value('csrf'),'action':'status','appointment_id':appointment_id,'status':'Cancelled'})
    check('Patient status updated.' in source and 'status-cancelled' in source,'Receptionist cancels an appointment')
    _,source,_,_=public.get(bookpath); p=Page(source)
    replacement={'csrf':p.value('csrf'),'submission_key':p.value('submission_key'),'date':date.isoformat(),'name':'Replacement Patient','phone':'03021234567','reason':''}
    status,slip,_,_=public.post(bookpath,replacement)
    check('>03</strong>' in slip and '6:35 PM' in slip,'Cancelled slot is refilled without reusing its token')
    printpath='/reception/print.php?doctor='+str(doctor)+'&date='+date.isoformat()
    status,source,_,_=reception.get(printpath)
    check(status==200 and 'PATIENT QUEUE LIST' in source and 'Print patient list' in source,'Authenticated full patient print list renders')
    tokens=re.findall(r'<strong>#(\d+)</strong>',source)
    check(tokens==['1','2','3'],'Print list preserves token order and cancelled bookings')
    check('/login.php' in stranger.get(printpath)[2],'Private patient print list requires staff authentication')
    for path in ['/database/schema.sql','/tests/run.php','/config.local.php','/includes/db.php']:
        check(stranger.get(path)[0] in [403,404],'Internal file blocked: '+path)
    from feature_cases import run_features
    run_features(globals())
    from chatbot_cases import run_chatbot_cases
    run_chatbot_cases(globals())
    _,source,_,_=admin.get('/admin/receptionists.php')
    row=next(row for row in re.findall(r'<tr>.*?</tr>',source,re.S) if prefix+'_receptionist' in row)
    rid=re.search(r'\?edit=(\d+)',row).group(1)
    status,source,_,_=admin.post('/admin/receptionists.php',{'csrf':Page(source).value('csrf'),'id':rid,'name':'Disabled QA','username':prefix+'_receptionist','phone':'03001234567','password':''})
    check('Receptionist account saved.' in source and '/login.php' in reception.get(queue)[2],'Deactivating receptionist invalidates an existing session')
    print(f'\n{passed} HTTP assertions passed.')
finally:
    fixture('cleanup')
