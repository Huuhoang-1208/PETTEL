const lockedStates = new Set(['AWAITING_PAYMENT','CONFIRMED','IN_PROGRESS','AWAITING_CONFIRMATION']);
export const isLocked = b => lockedStates.has(b.status);
export const parseDate = value => new Date(value).getTime();
export function quoteFor(listing,start,end,now) {
  if(!listing || listing.status!=='ACTIVE') return {ok:false,code:'LISTING_UNAVAILABLE',message:'Dịch vụ này hiện không nhận yêu cầu mới.'};
  const a=parseDate(start), z=parseDate(end), n=parseDate(now);
  if(!Number.isFinite(a)||!Number.isFinite(z)) return {ok:false,code:'DATE_REQUIRED',message:'Vui lòng chọn đủ thời gian bắt đầu và kết thúc.'};
  if(a<=n) return {ok:false,code:'START_PAST',message:'Thời gian bắt đầu cần ở sau giờ demo hiện tại.'};
  if(z<=a) return {ok:false,code:'RANGE_INVALID',message:'Thời gian kết thúc cần sau thời gian bắt đầu.'};
  const hours=(z-a)/3600000;
  let quantity,unit;
  if(listing.service==='DAYCARE') {
    if(hours<1 || hours>12) return {ok:false,code:'DURATION',message:'Chăm ban ngày cần từ 1 đến 12 giờ.'};
    if(new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Ho_Chi_Minh',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(a))!==new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Ho_Chi_Minh',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(z-1))) return {ok:false,code:'SAME_DAY',message:'Lượt chăm ban ngày cần trong cùng một ngày.'};
    quantity=Math.ceil(hours); unit='HOUR';
  } else {
    if(hours<12 || hours>24*30) return {ok:false,code:'DURATION',message:'Lưu trú cần từ 12 giờ đến 30 ngày.'};
    quantity=Math.ceil(hours/24); unit='NIGHT';
  }
  const slot=(listing.slots||[]).find(s=>parseDate(s.start)<=a&&parseDate(s.end)>=z);
  if(!slot) return {ok:false,code:'NO_SLOT',message:'Lịch mẫu chưa có một khung giờ liên tục bao trọn thời gian này.'};
  const total=listing.price*quantity;
  return {ok:true,quantity,unit,total,listingId:listing.id,slotId:slot.id};
}
export function matchListings(listings,pet,filters,now,bookings=[]) {
  if(!pet||pet.status!=='ACTIVE') return {ok:false,code:'PET_UNAVAILABLE',message:'Chọn một hồ sơ thú cưng đang hoạt động để tiếp tục.'};
  if(!pet.size) return {ok:false,code:'SIZE_REQUIRED',message:'Hồ sơ của '+pet.name+' chưa có kích cỡ. Hãy bổ sung trước khi dùng Smart Match.'};
  let rows=listings.filter(l=>l.status==='ACTIVE');
  if(filters.service) rows=rows.filter(l=>l.service===filters.service);
  if(filters.area) rows=rows.filter(l=>l.area===filters.area);
  rows=rows.filter(l=>l.species.includes(pet.species)&&l.sizes.includes(pet.size));
  let quoteInfo={};
  if(filters.start&&filters.end) rows=rows.filter(l=>{const q=quoteFor(l,filters.start,filters.end,now); if(!q.ok)return false; const overlap=bookings.some(b=>b.listingId===l.id&&isLocked(b)&&parseDate(b.start)<parseDate(filters.end)&&parseDate(b.end)>parseDate(filters.start)); if(overlap)return false; quoteInfo[l.id]=q;return true;});
  return {ok:true,rows,quoteInfo,reasons:['Loài phù hợp','Kích cỡ phù hợp',...(filters.start&&filters.end?['Có khung lịch mẫu','Tổng giá đã tính']:[])]};
}
export function canAccessBooking(b,persona) {
 return !!b && (persona==='owner'&&b.ownerId==='owner-01'||persona==='caregiver'&&b.caregiverId==='caregiver-01');
}
export function acceptBooking(state,id) {
 const b=state.bookings.find(x=>x.id===id), l=state.listings.find(x=>x.id===b?.listingId);
 if(!b||b.status!=='REQUESTED') return {ok:false,message:'Lượt đặt không còn ở trạng thái chờ nhận.'};
 if(l?.status!=='ACTIVE') return {ok:false,message:'Dịch vụ không còn hoạt động nên chưa thể nhận lượt đặt.'};
 if(parseDate(state.demoNow)>=Math.min(parseDate(b.createdAt)+24*3600000,parseDate(b.start))) return {ok:false,message:'Yêu cầu đã hết hạn; lịch không được giữ.'};
 const slot=(l.slots||[]).some(s=>parseDate(s.start)<=parseDate(b.start)&&parseDate(s.end)>=parseDate(b.end));
 if(!slot) return {ok:false,message:'Không còn khung giờ liên tục bao trọn lượt đặt.'};
 const conflict=state.bookings.some(x=>x.id!==id&&x.listingId===b.listingId&&isLocked(x)&&parseDate(x.start)<parseDate(b.end)&&parseDate(x.end)>parseDate(b.start));
 if(conflict) return {ok:false,message:'Lịch vừa có lượt đã giữ trùng giờ. Yêu cầu vẫn ở trạng thái chờ, chưa được nhận.'};
 b.status='AWAITING_PAYMENT'; b.paymentDueAt=new Date(parseDate(state.demoNow)+15*60000).toISOString();
 b.events.push({label:'Người chăm đã chấp nhận · giữ chỗ 15 phút',at:'08:00 · demo',actor:'Gia đình nhà Mây'});
 return {ok:true,booking:b};
}
export function rejectBooking(state,id,reason='Không thể nhận lịch này') {
 const b=state.bookings.find(x=>x.id===id); if(!b||b.status!=='REQUESTED')return {ok:false,message:'Chỉ có thể từ chối yêu cầu đang chờ.'};
 b.status='CANCELLED';b.cancelReason='CAREGIVER_REJECTED';b.events.push({label:'Người chăm từ chối: '+reason,at:'08:00 · demo',actor:'Gia đình nhà Mây'});return {ok:true,booking:b};
}
export function createBooking(state,{petId,listingId,start,end,instructions,key}) {
 if(state.ui.lastIdempotency?.key===key) {
   const old=state.bookings.find(b=>b.id===state.ui.lastIdempotency.bookingId);
   if(old&&state.ui.lastIdempotency.payload===JSON.stringify({petId,listingId,start,end,instructions}))return {ok:true,replay:true,booking:old};
   return {ok:false,message:'Khóa gửi lại đã dùng với nội dung khác. Hãy tạo một yêu cầu mới.'};
 }
 const pet=state.pets.find(p=>p.id===petId&&p.ownerId==='owner-01'&&p.status==='ACTIVE');
 const listing=state.listings.find(l=>l.id===listingId&&l.status==='ACTIVE');
 if(!pet)return {ok:false,message:'Hồ sơ thú cưng không hợp lệ hoặc không còn hoạt động.'};
 if(!listing)return {ok:false,message:'Dịch vụ không còn nhận yêu cầu.'};
 if(!listing.species.includes(pet.species)||!listing.sizes.includes(pet.size))return {ok:false,message:'Dịch vụ không nhận loài hoặc kích cỡ của thú cưng này.'};
 const q=quoteFor(listing,start,end,state.demoNow);if(!q.ok)return {ok:false,message:q.message};
 const occupied=state.bookings.some(b=>b.listingId===listingId&&isLocked(b)&&parseDate(b.start)<parseDate(end)&&parseDate(b.end)>parseDate(start));
 if(occupied)return {ok:false,message:'Khung giờ vừa được giữ bởi một lượt đặt khác.'};
 if(instructions.trim().length>2000)return {ok:false,message:'Ghi chú tối đa 2.000 ký tự.'};
 const id='booking-'+String(state.bookings.length+1).padStart(3,'0');
 const booking={id,ownerId:'owner-01',caregiverId:listing.caregiverId,petId,listingId,status:'REQUESTED',start,end,total:q.total,quantity:q.quantity,unit:q.unit,instructions:instructions.trim(),createdAt:state.demoNow,events:[{label:'Đã gửi yêu cầu · chưa giữ chỗ',at:'08:00 · demo',actor:'Minh Anh'}],payment:null,messages:[],updates:[],reviewId:null,idempotencyKey:key,snapshots:{petName:pet.name,petSpecies:pet.species,petSize:pet.size,listingTitle:listing.title,listingService:listing.service,price:listing.price,priceUnit:q.unit,priceQuantity:q.quantity,priceTotal:q.total}};
 state.bookings.unshift(booking);state.ui.lastIdempotency={key,bookingId:id,payload:JSON.stringify({petId,listingId,start,end,instructions})};return {ok:true,booking};
}
export function completePayment(state,b,scenario='success') {
 if(b.status!=='AWAITING_PAYMENT')return {ok:false,message:'Lượt đặt hiện không chờ thanh toán.'};
 if(parseDate(state.demoNow)>=parseDate(b.paymentDueAt))return {ok:false,message:'Thời gian giữ chỗ đã hết. Thanh toán không thể tiếp tục.'};
 b.payment ||= {attempts:[]}; const previous=b.payment.attempts.at(-1);
 if(scenario==='late')return {ok:false,message:'Callback đến muộn bị bỏ qua; booking và payment attempt giữ nguyên.'};
 if(previous&&previous.status==='PENDING') previous.status=scenario==='success'?'AUTHORIZED':'FAILED';
 else b.payment.attempts.push({id:'pay-'+Date.now(),status:scenario==='success'?'AUTHORIZED':'FAILED',at:'08:00 · demo'});
 if(scenario==='success'){b.status='CONFIRMED';b.events.push({label:'Thanh toán mô phỏng thành công · booking đã xác nhận',at:'08:00 · demo',actor:'Sandbox'});return {ok:true,message:'Thanh toán mô phỏng thành công. Không có khoản tiền thật được thu.'};}
 return {ok:false,message:'Thanh toán mô phỏng chưa thành công. Lượt đặt vẫn trong thời gian giữ chỗ; bạn có thể thử lại.'};
}
export function startCare(b){if(b.status!=='CONFIRMED')return {ok:false,message:'Chỉ có thể bắt đầu khi booking đã xác nhận.'};b.status='IN_PROGRESS';b.events.push({label:'Đã bắt đầu chăm sóc',at:'08:30 · demo',actor:'Gia đình nhà Mây'});return {ok:true};}
export function requestCompletion(state,b){if(b.status!=='IN_PROGRESS')return {ok:false,message:'Lượt đặt hiện không ở trạng thái đang chăm sóc.'};b.status='AWAITING_CONFIRMATION';b.completionRequestedAt=state.demoNow;b.events.push({label:'Người chăm đã gửi yêu cầu hoàn tất',at:'11:00 · demo',actor:'Gia đình nhà Mây'});return {ok:true};}
export function confirmCompletion(state,b,scenario='success') {
 if(b.status!=='AWAITING_CONFIRMATION')return {ok:false,message:'Chưa thể xác nhận hoàn tất ở trạng thái hiện tại.'};
 const held=state.reports.some(r=>r.targetType==='BOOKING'&&r.targetId===b.id&&r.status==='IN_REVIEW');if(held)return {ok:false,message:'Booking đang được xem xét bởi quản trị viên. Việc xác nhận hoàn tất đang tạm dừng.'};
 b.payment ||= {attempts:[]};b.payment.releaseOperationKey ||= 'release-'+b.id;b.payment.releaseAttempts=(b.payment.releaseAttempts||0)+1;b.payment.release=scenario==='success'?'SUCCEEDED':scenario==='pending'?'PENDING':'FAILED';if(scenario==='pending')return {ok:false,message:'Giải ngân đang xử lý. Booking vẫn chờ; chỉ cập nhật thành hoàn tất sau khi operation thành công.'};
 if(scenario==='success'){const auth=b.payment.attempts.find(a=>a.status==='AUTHORIZED');if(auth)auth.status='RELEASED';b.status='COMPLETED';b.events.push({label:'Đã hoàn tất · giải ngân mô phỏng thành công',at:'08:00 · demo',actor:'Sandbox'});return {ok:true,message:'Đã hoàn tất booking sau khi giải ngân mô phỏng thành công.'};}
 b.events.push({label:'Giải ngân mô phỏng lỗi · booking vẫn chờ xác nhận',at:'08:00 · demo',actor:'Sandbox'});return {ok:false,message:b.payment.releaseAttempts>=3?'Đã đạt ba lần thử. Booking vẫn chờ và cần Admin review.':'Giải ngân chưa thành công. Booking vẫn ở trạng thái chờ; có thể thử lại cùng thao tác.'};
}

export function cancelBooking(state,id,actor,scenario='success') {
 const b=state.bookings.find(x=>x.id===id);
 if(!b)return {ok:false,message:'Không tìm thấy booking.'};
 const isOwner=actor==='owner'&&b.ownerId==='owner-01';
 const isCaregiver=actor==='caregiver'&&b.caregiverId==='caregiver-01';
 if(!isOwner&&!isCaregiver)return {ok:false,message:'Bạn không có quyền hủy lượt đặt này.'};
 if(!['REQUESTED','AWAITING_PAYMENT','CONFIRMED'].includes(b.status))return {ok:false,message:'Không thể hủy booking ở trạng thái hiện tại.'};
 if(b.status==='REQUESTED') {
  if(!isOwner)return {ok:false,message:'Người chăm dùng thao tác từ chối cho yêu cầu chưa được nhận.'};
  b.status='CANCELLED';b.cancelReason='OWNER_CANCELLED';
  b.events.push({label:'Chủ nuôi đã hủy yêu cầu chưa được nhận',at:'08:00 · demo',actor:'Minh Anh'});
  return {ok:true,message:'Đã hủy yêu cầu chưa được nhận.'};
 }
 b.payment ||= {attempts:[]};b.payment.cancelOperationKey ||= 'cancel-'+b.id;
 if(scenario!=='success') {
  b.payment.cancelOperation='PENDING_RETRY';
  b.events.push({label:'Void/refund mô phỏng thất bại · booking vẫn giữ trạng thái',at:'08:00 · demo',actor:'Sandbox'});
  return {ok:false,message:'Operation chưa thành công; booking giữ trạng thái hiện tại và lịch sử được giữ.'};
 }
 const authorized=b.payment.attempts?.some(a=>a.status==='AUTHORIZED');
 b.payment.cancelOperation=authorized?'REFUNDED':'VOIDED';
 for(const attempt of b.payment.attempts||[]) if(attempt.status==='AUTHORIZED'||attempt.status==='PENDING')attempt.status=authorized?'REFUNDED':'VOIDED';
 b.status='CANCELLED';b.cancelReason='PARTICIPANT_CANCELLED';
 b.events.push({label:'Đã hủy sau khi void/refund mô phỏng thành công',at:'08:00 · demo',actor:isOwner?'Minh Anh':'Gia đình nhà Mây'});
 return {ok:true,message:'Đã hủy sau khi operation mô phỏng thành công.'};
}
export function expireBooking(state,id,scenario='success') {
 const b=state.bookings.find(x=>x.id===id);if(!b)return {ok:false,message:'Không tìm thấy booking.'};
 if(b.status==='REQUESTED') {b.status='CANCELLED';b.cancelReason='REQUEST_EXPIRED';b.events.push({label:'Yêu cầu đã hết hạn theo giờ demo',at:'08:00 · demo',actor:'Hệ thống demo'});return {ok:true,message:'Yêu cầu đã hết hạn.'};}
 if(b.status!=='AWAITING_PAYMENT')return {ok:false,message:'Booking không chờ thanh toán.'};
 b.payment ||= {attempts:[]};b.payment.cancelOperationKey ||= 'expiry-'+b.id;
 if(scenario!=='success'){b.payment.cancelOperation='PENDING_RETRY';return {ok:false,message:'Void đang chờ xử lý; booking vẫn giữ chỗ.'};}
 b.payment.cancelOperation='VOIDED';b.payment.expiry='EXPIRED';
 for(const attempt of b.payment.attempts||[])if(attempt.status==='PENDING')attempt.status='VOIDED';
 b.status='CANCELLED';b.cancelReason='PAYMENT_EXPIRED';b.events.push({label:'Giữ chỗ hết hạn sau void mô phỏng thành công',at:'08:15 · demo',actor:'Hệ thống demo'});
 return {ok:true,message:'Giữ chỗ hết hạn; operation void mô phỏng đã thành công.'};
}
export function advanceDemoClock(state,next) {
 const from=state.demoNow;state.demoNow=next;const expired=[];
 for(const b of state.bookings) {
  if(b.status==='AWAITING_PAYMENT'&&b.paymentDueAt&&parseDate(next)>=parseDate(b.paymentDueAt)){const result=expireBooking(state,b.id,'success');if(result.ok)expired.push(b.id);}
  else if(b.status==='REQUESTED'&&parseDate(next)>=Math.min(parseDate(b.createdAt)+24*3600000,parseDate(b.start))){const result=expireBooking(state,b.id,'success');if(result.ok)expired.push(b.id);}
 }
 return {ok:true,from,to:next,expired};
}
export function createReport(state,{reporterId,targetType,targetId,reason,description=''}) {
 if(!['owner-01','caregiver-01'].includes(reporterId))return {ok:false,message:'Persona này không thể gửi báo cáo.'};
 if(!targetType||!targetId||!reason)return {ok:false,message:'Mục tiêu và lý do báo cáo là bắt buộc.'};
 const report={id:'report-'+String(Date.now()).slice(-6),reporterId,targetType,targetId,reason,description:String(description).trim(),status:'OPEN',createdAt:state.demoNow||'2026-11-18T08:00:00+07:00',events:[{label:'Báo cáo đã được gửi',at:'08:00 · demo',actor:reporterId==='owner-01'?'Minh Anh':'Gia đình nhà Mây'}],operation:null};
 state.reports.unshift(report);return {ok:true,report};
}
export function startReportReview(state,id,actor,reason='') {
  const r=state.reports.find(x=>x.id===id),why=String(reason||'').trim();if(actor!=='admin')return {ok:false,message:'Chỉ quản trị viên ACTIVE được xem xét báo cáo.'};if(!r||r.status!=='OPEN')return {ok:false,message:'Báo cáo không còn ở trạng thái mới gửi.'};if(!why||why.length>1000)return {ok:false,message:'Lý do bắt đầu xem xét là bắt buộc.'};
  r.status='IN_REVIEW';r.events.push({label:`Bắt đầu xem xét · ${why}`,at:'08:00 · demo',actor:'Quản trị viên demo'});adminAudit(state,'REPORT_START_REVIEW',r.id,why);return {ok:true,report:r};
}
export function decideReport(state,id,actor,{decision,reason,operationResult='success'}) {
  const r=state.reports.find(x=>x.id===id);if(actor!=='admin')return {ok:false,message:'Chỉ quản trị viên ACTIVE được quyết định báo cáo.'};
  const why=String(reason||'').trim();if(!r||!['OPEN','IN_REVIEW'].includes(r.status))return {ok:false,message:'Báo cáo không còn mở để quyết định.'};if(!why||why.length>1000)return {ok:false,message:'Lý do quyết định là bắt buộc (tối đa 1.000 ký tự).'};
  const b=r.targetType==='BOOKING'?state.bookings.find(x=>x.id===r.targetId):null;
  if(decision==='DISMISS') {r.status='DISMISSED';r.events.push({label:`Đã dismiss report · ${why}`,at:'08:00 · demo',actor:'Quản trị viên demo'});adminAudit(state,'REPORT_DISMISSED',r.id,why);return {ok:true,message:'Report đã đóng; booking và payment không đổi.'};}
  if(r.status!=='IN_REVIEW')return {ok:false,message:'Bắt đầu xem xét trước khi quyết định.'};
  if(b&&['COMPLETE','CANCEL_REFUND'].includes(decision)) {
   const attempt=b.payment?.attempts?.find(x=>x.status==='AUTHORIZED');if(!attempt)return {ok:false,message:'Booking cần payment attempt AUTHORIZED.'};
   if(decision==='COMPLETE'&&b.status!=='AWAITING_CONFIRMATION')return {ok:false,message:'Chỉ booking chờ xác nhận hoàn tất mới được quyết định COMPLETE.'};
   if(decision==='CANCEL_REFUND'&&!['IN_PROGRESS','AWAITING_CONFIRMATION'].includes(b.status))return {ok:false,message:'CANCEL_REFUND chỉ áp dụng cho booking đang chăm sóc hoặc chờ xác nhận hoàn tất.'};
   const active=(state.adminOperations||[]).some(o=>o.bookingId===b.id&&['PENDING','PROCESSING','MANUAL_REVIEW'].includes(o.status));if(active)return {ok:false,message:'Booking đang có operation cần xử lý; không tạo operation trùng.'};
   const kind=decision==='COMPLETE'?'RELEASE':'CANCEL_REFUND',operation={id:`op-${r.id}`,kind,status:'PENDING',bookingId:b.id,reportId:r.id,reason:why,stableKey:`pettel-demo-${r.id}`,attemptCount:0,createdAt:state.demoNow,lastError:null,events:[{label:'Admin tạo operation · PENDING',at:state.demoNow,actor:'Quản trị viên demo'}]};
   r.operation=operation;r.status='RESOLVED';r.events.push({label:`Đã quyết định ${decision}; operation được tạo và đang chờ worker`,at:'08:00 · demo',actor:'Quản trị viên demo'});state.adminOperations ||= [];state.adminOperations.push(operation);adminAudit(state,'BOOKING_REPORT_OPERATION_CREATED',r.id,why);
   return {ok:true,message:'Report đã RESOLVED khi operation được tạo. Booking/payment chưa đổi; kết quả chờ worker.'};
  }
  if(!b&&decision==='RESOLVE'){r.status='RESOLVED';r.events.push({label:`Đã resolve report · ${why}`,at:'08:00 · demo',actor:'Quản trị viên demo'});adminAudit(state,'REPORT_RESOLVED',r.id,why);return {ok:true,message:'Report đã được resolve.'};}
  return {ok:false,message:'Lựa chọn không hợp lệ với loại report này.'};
}

function adminAudit(state,action,targetId,reason){state.adminAudit ||= [];state.adminAudit.unshift({action,targetId,reason,actor:'Quản trị viên demo',at:state.demoNow||'08:00 · demo'});}
export function moderateReportedTarget(state,reportId,actor,action,reason='',expectedVersion=null) {
 const r=state.reports.find(x=>x.id===reportId),why=String(reason||'').trim();if(actor!=='admin')return {ok:false,message:'Chỉ quản trị viên ACTIVE được kiểm duyệt.'};if(!r||r.status!=='IN_REVIEW')return {ok:false,message:'Report phải ở IN_REVIEW.'};if(!['HIDE','RESTORE'].includes(action)||!why||why.length>1000)return {ok:false,message:'Thao tác hoặc lý do không hợp lệ.'};
 if(r.targetType==='LISTING') {const l=state.listings.find(x=>x.id===r.targetId);if(!l)return {ok:false,message:'Không tìm thấy listing.'};if(expectedVersion!==null&&Number(expectedVersion)!==Number(l.version))return {ok:false,message:'Phiên bản listing đã thay đổi; tải lại trước khi thao tác.'};if(action==='HIDE'){if(!['ACTIVE','SUSPENDED'].includes(l.status)||l.status==='SUSPENDED'&&l.suspensionSource==='ADMIN')return {ok:false,message:'Listing không ở trạng thái có thể ẩn.'};l.status='SUSPENDED';l.suspensionSource='ADMIN';l.version=(l.version||1)+1;}else{if(l.status!=='SUSPENDED'||l.suspensionSource!=='ADMIN')return {ok:false,message:'Chỉ có thể khôi phục listing do Admin tạm ẩn.'};l.status='ACTIVE';l.suspensionSource=null;l.version=(l.version||1)+1;}l.moderationEvents ||= [];l.moderationEvents.push({label:`${action==='HIDE'?'Ẩn':'Khôi phục'} qua report · ${why}`,at:'08:00 · demo',actor:'Quản trị viên demo'});}
 else if(r.targetType==='REVIEW'){const review=state.reviews.find(x=>x.id===r.targetId);if(!review)return {ok:false,message:'Không tìm thấy review.'};review.status=action==='HIDE'?'HIDDEN':'VISIBLE';review.moderationEvents ||= [];review.moderationEvents.push({label:`${action==='HIDE'?'Ẩn':'Khôi phục'} qua report · ${why}`,at:'08:00 · demo',actor:'Quản trị viên demo'});}
 else return {ok:false,message:'Backend không hỗ trợ Hide/Restore cho target này.'};
 r.events.push({label:`Target ${action==='HIDE'?'ẩn':'khôi phục'} · ${why}`,at:'08:00 · demo',actor:'Quản trị viên demo'});adminAudit(state,`REPORT_TARGET_${action}`,r.id,why);return {ok:true,message:`Đã ${action==='HIDE'?'ẩn':'khôi phục'} target và ghi audit demo.`};
}

export function createAdminCompletionOperation(state,bookingId,actor,reason='') {
 const b=state.bookings.find(x=>x.id===bookingId),why=String(reason||'').trim();if(actor!=='admin')return {ok:false,message:'Chỉ quản trị viên ACTIVE được thực hiện.'};if(!b||b.status!=='AWAITING_CONFIRMATION')return {ok:false,message:'Booking không ở trạng thái chờ xác nhận hoàn tất.'};if(!why||why.length>1000)return {ok:false,message:'Lý do là bắt buộc (tối đa 1.000 ký tự).'};if(!b.payment?.attempts?.some(x=>x.status==='AUTHORIZED'))return {ok:false,message:'Booking cần payment attempt AUTHORIZED.'};
 const requested=Date.parse(b.completionRequestedAt||'');if(!Number.isFinite(requested)||Date.parse(state.demoNow)-requested<72*60*60*1000)return {ok:false,message:'Booking chưa quá hạn follow-up 72 giờ.'};
 state.adminOperations ||= [];if(state.adminOperations.some(o=>o.bookingId===b.id&&o.kind==='RELEASE'&&['PENDING','PROCESSING','MANUAL_REVIEW','COMPLETED'].includes(o.status)))return {ok:false,message:'Booking đã có release operation; không tạo thao tác trùng.'};
 const op={id:`release-${b.id}`,kind:'RELEASE',status:'PENDING',bookingId:b.id,reason:why,stableKey:`pettel-demo-release-${b.id}`,attemptCount:0,createdAt:state.demoNow,lastError:null,events:[{label:'Admin tạo RELEASE operation · PENDING',at:state.demoNow,actor:'Quản trị viên demo'}]};state.adminOperations.push(op);adminAudit(state,'COMPLETION_REVIEW_OPERATION_CREATED',b.id,why);return {ok:true,operation:op,message:'Đã tạo release operation PENDING. Booking vẫn AWAITING_CONFIRMATION cho tới kết quả worker.'};
}

export function retryAdminCancellation(state,operationId,actor,reason='') {
 const op=(state.adminOperations||[]).find(x=>x.id===operationId),why=String(reason||'').trim();if(actor!=='admin')return {ok:false,message:'Chỉ quản trị viên ACTIVE được retry.'};if(!op||op.kind!=='CANCEL_REFUND'||op.status!=='MANUAL_REVIEW')return {ok:false,message:'Chỉ cancellation operation MANUAL_REVIEW được retry.'};if(op.reasonCode==='PAYMENT_STATE_MISMATCH')return {ok:false,message:'Cần đối soát trạng thái payment trước khi retry.'};if(why.length<10||why.length>500)return {ok:false,message:'Lý do retry cần từ 10 đến 500 ký tự.'};op.status='PENDING';op.attemptCount=0;op.lastError=null;op.retryReason=why;op.lastRetriedAt=state.demoNow;op.events ||= [];op.events.push({label:`Retry → PENDING · ${why}`,at:state.demoNow,actor:'Quản trị viên demo'});adminAudit(state,'CANCELLATION_OPERATION_RETRIED',op.id,why);return {ok:true,message:'Đã đưa cancellation operation về PENDING; booking chưa đổi trạng thái.'};
}

export function simulateAdminWorker(state,operationId,actor,result='success') {
 const op=(state.adminOperations||[]).find(x=>x.id===operationId);if(actor!=='admin')return {ok:false,message:'Chỉ quản trị viên ACTIVE được chạy mô phỏng.'};if(!op||op.status!=='PENDING')return {ok:false,message:'Chỉ operation PENDING mới có thể mô phỏng kết quả worker.'};const b=state.bookings.find(x=>x.id===op.bookingId);if(!b)return {ok:false,message:'Không tìm thấy booking.'};
 op.events ||= [];if(result!=='success'){op.status='MANUAL_REVIEW';op.attemptCount=(op.attemptCount||0)+1;op.lastError='Worker demo không xác nhận được kết quả operation.';op.lastAttemptAt=state.demoNow;op.events.push({label:'Worker demo → MANUAL_REVIEW',at:state.demoNow,actor:'Worker mô phỏng'});adminAudit(state,'MOCK_WORKER_FAILURE',op.id,'Kết quả worker demo thất bại');if(op.reportId){const report=state.reports.find(x=>x.id===op.reportId);if(report){report.events.push({label:'Operation chuyển MANUAL_REVIEW từ kết quả worker demo',at:state.demoNow,actor:'Worker mô phỏng'});if(report.operation)Object.assign(report.operation,op);}}return {ok:true,message:'Worker demo chuyển operation sang MANUAL_REVIEW; booking/payment chưa đổi.'};}
 op.status='COMPLETED';op.attemptCount=(op.attemptCount||0)+1;op.completedAt=state.demoNow;op.lastError=null;op.events.push({label:'Worker demo → COMPLETED',at:state.demoNow,actor:'Worker mô phỏng'});b.payment ||= {attempts:[]};if(op.kind==='RELEASE'){b.payment.release='SUCCEEDED';for(const x of b.payment.attempts||[])if(x.status==='AUTHORIZED')x.status='RELEASED';b.status='COMPLETED';b.events.push({label:'Release operation hoàn tất · worker demo',at:'08:00 · demo',actor:'Worker mô phỏng'});}else{b.status='CANCELLED';b.cancelReason='ADMIN_CANCEL_REFUND';b.payment.refund='SUCCEEDED';for(const x of b.payment.attempts||[])if(['AUTHORIZED','PENDING'].includes(x.status))x.status='REFUNDED';b.events.push({label:'Cancellation operation hoàn tất · worker demo',at:'08:00 · demo',actor:'Worker mô phỏng'});}if(op.reportId){const report=state.reports.find(x=>x.id===op.reportId);if(report){report.events.push({label:'Operation hoàn tất từ kết quả worker demo',at:state.demoNow,actor:'Worker mô phỏng'});if(report.operation)Object.assign(report.operation,op);}}adminAudit(state,'MOCK_WORKER_COMPLETED',op.id,'Kết quả thành công từ worker mô phỏng');return {ok:true,message:'Worker demo hoàn tất operation; trạng thái booking chỉ đổi sau kết quả này.'};
}
export function submitListing(state,id,actor) {
 const l=state.listings.find(x=>x.id===id);if(actor!=='caregiver'||l?.caregiverId!=='caregiver-01')return {ok:false,message:'Chỉ người chăm sở hữu dịch vụ mới có thể gửi duyệt.'};
 if(l.status!=='DRAFT')return {ok:false,message:'Chỉ bản nháp mới được gửi duyệt.'};if(!l.title||!Number.isInteger(Number(l.price))||Number(l.price)<1||!l.species?.length||!l.sizes?.length)return {ok:false,message:'Lưu tên, giá, loài và kích cỡ trước khi gửi duyệt.'};
 l.status='PENDING_REVIEW';return {ok:true,message:'Đã gửi duyệt; chờ quản trị xem.'};
}
export function moderateListing(state,id,actor,decision,reason='',expectedVersion=null) {
  const l=state.listings.find(x=>x.id===id);if(actor!=='admin')return {ok:false,message:'Chỉ quản trị viên ACTIVE được quyết định listing.'};if(l?.status!=='PENDING_REVIEW')return {ok:false,message:'Chỉ listing đang chờ duyệt mới có thể quyết định.'};if(expectedVersion!==null&&Number(expectedVersion)!==Number(l.version))return {ok:false,message:'Phiên bản listing đã thay đổi; tải lại trước khi quyết định.'};
 if(!String(reason).trim())return {ok:false,message:'Cần ghi lý do quyết định để lưu audit.'};
  l.status=decision==='approve'?'ACTIVE':'DRAFT';if(decision==='approve')l.version++;l.moderationReason=decision==='reject'?reason:'';l.moderationEvents=l.moderationEvents||[];l.moderationEvents.push({label:decision==='approve'?'Đã duyệt':'Đã từ chối: '+reason,at:'08:00 · demo',actor:'Quản trị viên demo'});adminAudit(state,`LISTING_${decision.toUpperCase()}`,id,reason);return {ok:true,message:decision==='approve'?'Dịch vụ đã được duyệt demo.':'Đã từ chối và lưu lý do demo.'};
}
export function changeListingStatus(state,id,actor,action,reason='',expectedVersion=null) {
 const l=state.listings.find(x=>x.id===id);if(!l)return {ok:false,message:'Không tìm thấy listing.'};
 if(!['caregiver','admin'].includes(actor)||actor==='caregiver'&&l.caregiverId!=='caregiver-01')return {ok:false,message:'Không có quyền thay đổi listing này.'};
  if(expectedVersion!==null&&Number(expectedVersion)!==Number(l.version))return {ok:false,message:'Phiên bản listing đã thay đổi; tải lại trước khi thao tác.'};
 const next={suspend:'SUSPENDED',restore:'ACTIVE',archive:'ARCHIVED'}[action];if(!next)return {ok:false,message:'Thao tác không hợp lệ.'};
 if(action==='suspend'&&l.status!=='ACTIVE'||action==='restore'&&l.status!=='SUSPENDED'||action==='archive'&&!['ACTIVE','SUSPENDED'].includes(l.status))return {ok:false,message:'Listing không ở trạng thái cho phép thao tác này.'};
  if(actor==='caregiver'&&action==='restore'&&l.suspensionSource==='ADMIN')return {ok:false,message:'Chỉ Admin có thể khôi phục listing do Admin tạm ẩn.'};
 const locked=state.bookings.some(b=>b.listingId===id&&isLocked(b));if(action==='archive'&&locked)return {ok:false,message:'Không thể lưu trữ khi dịch vụ còn booking đã giữ lịch.'};
  if(actor==='admin'&&!String(reason).trim())return {ok:false,message:'Cần có lý do để ghi audit.'};
  if(actor==='admin'&&action==='restore'&&l.suspensionSource!=='ADMIN')return {ok:false,message:'Chỉ có thể khôi phục listing do Admin tạm ẩn.'};
  if(actor==='admin'&&action==='suspend'&&l.status==='SUSPENDED'&&l.suspensionSource==='ADMIN')return {ok:true,message:'Listing đã được Admin tạm ẩn.'};
  l.status=next;l.moderationReason=reason;l.moderatedBy=actor==='admin'?'Quản trị viên demo':'Gia đình nhà Mây';if(actor==='admin'){l.suspensionSource=action==='suspend'?'ADMIN':null;l.version=(l.version||1)+1;}else if(action==='suspend')l.suspensionSource='CAREGIVER';else if(action==='restore')l.suspensionSource=null;l.moderationEvents=l.moderationEvents||[];l.moderationEvents.push({label:`${action} → ${next}${reason?': '+reason:''}`,at:'08:00 · demo',actor:l.moderatedBy});if(actor==='admin')adminAudit(state,`LISTING_${action.toUpperCase()}`,id,reason);return {ok:true,message:'Đã cập nhật trạng thái demo.'};
}
