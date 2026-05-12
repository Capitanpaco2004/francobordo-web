$(document).ready(function(){

var message = $('.toolbarHead');
var view = $(window);
// bind only if message exists. placeholder will be its parent
view.bind("scroll resize", function(e)
{
message.each(function(el){
if (message.length)
{
placeholder = $(this).parent();
//if(e.type == 'resize')
//$(this).css('width', $(this).parent().width());
placeholderTop = placeholder.offset().top;
var viewTop = view.scrollTop() + 15;
// here we force the tlbr to be "not fixed" when
// the height of the window is really small (tlbr hiding the page is not cool)
window_is_more_than_twice_the_tlbr = view.height() > message.parent().height() * 2;
if (!$(this).hasClass("fix-tlbr") && (window_is_more_than_twice_the_tlbr && (viewTop > placeholderTop)))
{
//$(this).css('width', $(this).width());
// fixing parent height will prevent that annoying "pagequake" thing
// the order is important : this has to be set before adding class fix-tlbr
$(this).parent().css('height', $(this).parent().height());
$(this).addClass("fix-tlbr");
}
else if ($(this).hasClass("fix-tlbr") && (!window_is_more_than_twice_the_tlbr || (viewTop <= placeholderTop)) )
{
$(this).removeClass("fix-tlbr");
$(this).removeAttr('style');
$(this).parent().removeAttr('style');
}
}
});
	
}); // end bind
	
});