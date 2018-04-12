using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Web.UI;
using System.Web.UI.WebControls;
using System.ServiceProcess;

public partial class _Default : System.Web.UI.Page
    {
        protected void Page_Load(object sender, EventArgs e)
        {
            string servicestatus = checkservice();
            Response.Write("{ \"records\":[ {\"servicestatus\": \"" + servicestatus+ "\"}]}");
        }

        string checkservice()
        {
            try
            {
                ServiceController sc = new ServiceController("KTHB Handler");
                switch (sc.Status)
                {
                    case ServiceControllerStatus.Running:
                        return "OK";
                    case ServiceControllerStatus.Stopped:
                        return "Stoppad";
                    case ServiceControllerStatus.Paused:
                        return "Pausad";
                    case ServiceControllerStatus.StopPending:
                        return "Stopping";
                    case ServiceControllerStatus.StartPending:
                        return "Starting";
                    default:
                        return "Status Changing";
                }
            }
            catch (Exception e)
            {
                //Response.Write(e.Message);
                return e.Message;
            }
        }
    }