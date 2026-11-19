# Bản Python phản chiếu age.php để kiểm nhanh (sandbox không có PHP).
import csv, calendar, datetime as dt, os
D=os.path.join(os.path.dirname(__file__),"..","data")
def anniv(dob,n):
    y=dob.year+(dob.month-1+n)//12; m=(dob.month-1+n)%12+1
    if dob.day>calendar.monthrange(y,m)[1]:
        return dt.date(y+(m==12),m%12+1,1)
    return dt.date(y,m,dob.day)
def age(dob,t):
    n=(t.year-dob.year)*12+t.month-dob.month
    while n>0 and anniv(dob,n)>t:n-=1
    while anniv(dob,n+1)<=t:n+=1
    return n
def match(a,mn,mx): return None if mn=="" else a>=int(mn) and (mx=="" or a<int(mx))
for c,e in [((2026,5,10),(2026,11,10)),((2026,5,10),(2026,11,9)),((2026,1,31),(2026,2,28)),((2026,1,31),(2026,3,1)),((2024,2,29),(2025,2,28)),((2024,2,29),(2025,3,1)),((2026,11,18),(2026,11,18))]:
    print("sinh",dt.date(*c),"| ngay",dt.date(*e),"=",age(dt.date(*c),dt.date(*e)),"thang")
lab=list(csv.DictReader(open(D+"/sample_age_labels.csv",encoding="utf-8-sig")))
fails=0
for r in csv.DictReader(open(D+"/expected_filter_cases.csv",encoding="utf-8-sig")):
    a=int(r["child_age_months"]); got=sorted(x["sku"] for x in lab if match(a,x["age_min_months"],x["age_max_months_exclusive"]))
    ok=got==sorted(r["expected_labeled_skus"].split()); fails+=not ok
    print(a,"OK" if ok else "FAIL",got if not ok else "")
print("FAILS:",fails)